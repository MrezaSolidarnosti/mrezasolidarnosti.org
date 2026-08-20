<?php
namespace Solidarity\Frontend\Service;

use Skeletor\Core\Config\Config;
use Solidarity\Page\Repository\PageRepository;

/**
 * Frontend locale resolver.
 *
 * The default locale is served at the URL root (no prefix); every other available
 * locale lives under its own path prefix, e.g. default `sr` → `/o-nama`, `en` →
 * `/en/o-nama`. This service detects the active locale from the request path,
 * strips the prefix so the router matches the same base routes for every language,
 * and builds prefixed URLs for the language switcher / hreflang tags.
 *
 * Registered as a shared (singleton) DI service: `detectFromRequest()` runs once
 * in the frontend entrypoint, and the same instance is later injected into actions
 * so templates can read the resolved locale.
 */
class Locale
{
    private string $default;
    /** @var string[] */
    private array $available;

    private string $current;
    /** Request path with the locale prefix removed (always starts with '/'). */
    private string $basePath = '/';

    /** @var array<string, ?string> source slug => translated slug, memoized per request. */
    private array $slugCache = [];

    public function __construct(Config $config, private PageRepository $pages)
    {
        $locales = $config->get('locales');
        $this->default = $locales?->get('default') ?: 'sr';
        $available = $locales?->get('available');
        $this->available = $available ? $available->toArray() : [$this->default];
        $this->current = $this->default;
    }

    /**
     * Read the active locale from $_SERVER['REQUEST_URI'], remember it and the
     * de-prefixed base path, then rewrite REQUEST_URI in place so the router
     * dispatches the language-agnostic route. Call once, early, per request.
     */
    public function detectFromRequest(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $query = '';
        if (($pos = strpos($requestUri, '?')) !== false) {
            $query = substr($requestUri, $pos);          // includes leading '?'
            $requestUri = substr($requestUri, 0, $pos);
        }

        $this->current = $this->parse($requestUri);

        // Forms submit to unprefixed action URLs (e.g. POST /donor/login), so a non-GET
        // request carries no locale in its path and would fall back to the default. Recover
        // the locale from the Referer — the locale-prefixed page the form was submitted from —
        // so redirect targets and t() messages resolve in the user's language.
        if ($this->current === $this->default
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
            && !empty($_SERVER['HTTP_REFERER'])) {
            $this->current = $this->parse((string) parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));
        }

        $this->basePath = $this->strip($requestUri);

        $_SERVER['REQUEST_URI'] = $this->basePath . $query;
    }

    /** Locale encoded in the given path, or the default when none/unknown. */
    public function parse(string $path): string
    {
        $first = strtok(trim($path, '/'), '/');
        if ($first && $first !== $this->default && in_array($first, $this->available, true)) {
            return $first;
        }
        return $this->default;
    }

    /** The given path with a leading locale prefix removed. */
    public function strip(string $path): string
    {
        $locale = $this->parse($path);
        if ($locale === $this->default) {
            return $path === '' ? '/' : $path;
        }
        $stripped = substr($path, strlen('/' . $locale));
        return $stripped === '' ? '/' : $stripped;
    }

    /**
     * Build a public URL for $path (a de-prefixed, root-relative path) in $locale.
     * Default locale → no prefix; others → '/{locale}' prefix.
     */
    public function localize(string $path, ?string $locale = null): string
    {
        $locale = $locale ?? $this->current;
        $path = '/' . ltrim($path, '/');
        if ($locale === $this->default) {
            return $path;
        }
        return '/' . $locale . ($path === '/' ? '' : $path);
    }

    /**
     * Ensure an internal link is absolute (leading slash) so it can't resolve relative
     * to the current path (e.g. a slug-less `doniraj` on `/en/x` → `/en/en/doniraj`).
     * External links (with a scheme), protocol-relative `//`, anchors `#…` and empty
     * values are returned unchanged.
     */
    public static function absolutePath(string $url): string
    {
        if ($url === '' || $url[0] === '/' || $url[0] === '#'
            || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) {
            return $url;
        }
        return '/' . $url;
    }

    /**
     * Localize an internal link for the active locale, translating the leading page
     * slug to its counterpart in the current language (sr `/doniraj` → en `/donate`).
     * Used by the localizeUrl() template helper for menu/content links that are
     * authored with default-locale slugs.
     *
     * - External, protocol-relative and non-root links pass through unchanged.
     * - Default locale: returned as-is (its slugs are the canonical, authored form).
     * - A page with no translation in the current locale keeps its original link, so
     *   it still resolves (served in the default locale) instead of 404-ing.
     * - The home path and non-page routes are only prefixed, never slug-translated.
     */
    public function localizeUrl(string $url): string
    {
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }
        if ($this->current === $this->default) {
            return $url;
        }

        // Split the path from any ?query / #fragment so only the path is translated.
        $cut = strcspn($url, '?#');
        $path = substr($url, 0, $cut);
        $suffix = substr($url, $cut);

        $slug = trim($path, '/');
        if ($slug === '') {
            return $this->localize('/') . $suffix; // home: prefix only
        }

        $translated = $this->translateSlug($slug);
        if ($translated !== null) {
            return $this->localize('/' . $translated) . $suffix;
        }

        // No translation for this page (or not a page at all): leave the link as-is.
        return $url;
    }

    /** Translated slug for the current locale, or null when there is none. Memoized. */
    private function translateSlug(string $slug): ?string
    {
        if (!array_key_exists($slug, $this->slugCache)) {
            $this->slugCache[$slug] = $this->pages->findTranslatedSlug($slug, $this->default, $this->current);
        }
        return $this->slugCache[$slug];
    }

    /** [locale => url] for the current page in every available locale (switcher/hreflang). */
    public function alternates(): array
    {
        $urls = [];
        foreach ($this->available as $locale) {
            $urls[$locale] = $this->localize($this->basePath, $locale);
        }
        return $urls;
    }

    public function current(): string
    {
        return $this->current;
    }

    public function default(): string
    {
        return $this->default;
    }

    /** @return string[] */
    public function available(): array
    {
        return $this->available;
    }

    public function isDefault(): bool
    {
        return $this->current === $this->default;
    }

    /** The de-prefixed request path (root-relative). */
    public function basePath(): string
    {
        return $this->basePath;
    }
}
