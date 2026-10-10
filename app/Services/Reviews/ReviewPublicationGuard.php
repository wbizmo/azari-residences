<?php

namespace App\Services\Reviews;

class ReviewPublicationGuard
{
    /**
     * Detect contact information before review publication. We require
     * guest edits rather than silently mutating a guest's words.
     */
    public function containsContactDetails(array $fields): bool
    {
        foreach (['title', 'body', 'positive_feedback', 'negative_feedback'] as $field) {
            $text = (string) ($fields[$field] ?? '');
            if ($text === '') {
                continue;
            }

            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text)
                || preg_match('~(?:https?://|www\.)[^\s]+~i', $text)) {
                return true;
            }

            if (preg_match_all('/(?<!\w)\+?\d[\d\s().-]{7,}\d(?!\w)/u', $text, $matches)) {
                foreach ($matches[0] as $candidate) {
                    $digits = preg_replace('/\D/', '', $candidate);
                    if (strlen($digits) >= 9 && strlen($digits) <= 15) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
