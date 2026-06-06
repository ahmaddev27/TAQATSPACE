<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the per-request locale so the API can answer in the caller's
 * language. Honours an explicit `X-Locale` header first, then negotiates the
 * standard `Accept-Language` header. Falls back to the application default
 * (Arabic) whenever the requested locale is missing or unsupported.
 */
final class SetLocale
{
    /**
     * Locales the API is allowed to serve. Anything outside this list falls
     * back to the application default.
     *
     * @var list<string>
     */
    private const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        $explicit = $this->normalize($request->header('X-Locale'));

        if ($explicit !== null) {
            return $explicit;
        }

        return $this->negotiateAcceptLanguage($request->header('Accept-Language'));
    }

    /**
     * Pick the first supported locale from a comma-separated Accept-Language
     * header (quality values are ignored — first match wins, which mirrors the
     * two-locale nature of this app).
     */
    private function negotiateAcceptLanguage(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $tag = trim(explode(';', $part)[0]);
            $locale = $this->normalize($tag);

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Reduce a language tag (e.g. "en-US") to its primary subtag and validate
     * it against the supported set.
     */
    private function normalize(?string $tag): ?string
    {
        if ($tag === null) {
            return null;
        }

        $primary = strtolower(trim(explode('-', $tag)[0]));

        return in_array($primary, self::SUPPORTED, true) ? $primary : null;
    }
}
