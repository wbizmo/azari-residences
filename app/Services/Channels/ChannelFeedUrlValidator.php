<?php

namespace App\Services\Channels;

use InvalidArgumentException;

class ChannelFeedUrlValidator
{
    public function assertSafe(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['https', 'http'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true))) {
            throw new InvalidArgumentException('The calendar URL must be a public HTTP or HTTPS URL without embedded credentials.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Private calendar hosts are not permitted.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('The calendar host could not be resolved safely.');
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('Private or reserved calendar addresses are not permitted.');
            }
        }

        // Pin the transport to this validated IP. DNS validation followed by
        // an ordinary HTTP request permits a DNS-rebinding TOCTOU attack.
        return $ips[0];
    }

    /** @return string[] */
    private function resolve(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip) $ips[] = $ip;
        }
        return array_values(array_unique($ips));
    }
}
