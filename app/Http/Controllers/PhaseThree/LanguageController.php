<?php
namespace App\Http\Controllers\PhaseThree;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale'=>['required', Rule::in(array_keys(config('localization.supported_locales', [])))]]);
        $locale = $data['locale'];
        $request->session()->put('locale', $locale);
        if ($user = $request->user()) {
            $user->forceFill(['locale'=>$locale])->save();
        }
        // Laravel's previous URL may come from a third-party Referer. Restrict
        // redirects to our own origin; never use arbitrary return URLs.
        $previous = url()->previous();
        $origin = parse_url(config('app.url'), PHP_URL_HOST);
        $host = parse_url($previous, PHP_URL_HOST);
        return redirect($origin && $origin === $host ? $previous : route('home'));
    }
}
