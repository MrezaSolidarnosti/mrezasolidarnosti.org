<?php

declare(strict_types=1);

namespace Solidarity\Frontend\Service;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Skeletor\ContentEditor\Contracts\BlockViewInterface;

/**
 * Normalises and locale-swaps the link fields of a CMS block before it reaches its template.
 *
 * Block templates echo their link fields raw, so a link authored in the admin as "doniraj" or
 * "/doniraj" would otherwise reach the page as typed: without the leading slash it resolves
 * relative to the current path, so the same link means /en/doniraj on one page and /doniraj on
 * another. On a non-default locale it also has to be swapped for the localised slug.
 *
 * A decorator rather than a change to the block views themselves — every block would otherwise
 * need to remember to do this, and the ones that forgot would fail silently.
 */
final class LocalizingBlockView implements BlockViewInterface
{
    /**
     * Keys whose value is a link. Matched by suffix, case-insensitively: too loose and the
     * decorator rewrites prose, too tight and a link silently keeps the Serbian slug on an
     * English page. "urlencode" is deliberately not a match — "url" is a prefix there.
     */
    private const LINK_KEY_SUFFIXES = ['url', 'link', 'href'];

    public function __construct(
        private readonly BlockViewInterface $inner,
        private readonly Locale $locale,
    ) {}

    public function getView(array $data = []): string
    {
        return $this->inner->getView($this->localizeLinks($data));
    }

    /**
     * Stays transparent, or every block filter silently stops being registered and the whole
     * CMS renders with unfiltered data.
     */
    public function registerViewFilter(string $name, BlockViewFilterInterface $blockViewFilter): void
    {
        $this->inner->registerViewFilter($name, $blockViewFilter);
    }

    /**
     * @param array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    private function localizeLinks(array $data): array
    {
        foreach ($data as $key => $value) {
            // Blocks nest: ctabanner holds buttons[], projectsdisplay holds projects[].
            if (is_array($value)) {
                $data[$key] = $this->localizeLinks($value);
                continue;
            }

            if (!is_string($value) || $value === '' || !is_string($key) || !$this->namesALink($key)) {
                continue;
            }

            $data[$key] = $this->localizeLink($value);
        }

        return $data;
    }

    private function namesALink(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::LINK_KEY_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function localizeLink(string $value): string
    {
        // Anchors, protocol-relative URLs and anything carrying a scheme (https:, mailto:) point
        // outside this site's routing and are left exactly as authored.
        if ($value[0] === '#' || str_starts_with($value, '//') || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $value)) {
            return $value;
        }

        $absolute = str_starts_with($value, '/') ? $value : '/' . $value;

        // Default-locale slugs are the authored form, so a lookup per link would be waste.
        return $this->locale->isDefault() ? $absolute : $this->locale->localizeUrl($absolute);
    }
}
