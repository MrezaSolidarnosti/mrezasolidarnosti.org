<?php

namespace Solidarity\ContentEditor\BlockViewFilters;

use Solidarity\ContentEditor\Contracts\BlockViewFilterInterface;

/**
 * The embed block stores the URL the admin pasted, not the embeddable one - the editor
 * resolves it in JS every time it renders. The frontend has to do the same, so this is a
 * port of Embed.PROVIDERS (skeletorJS ContentEditor/Blocks/Embed/Embed.js): keep the two
 * in step when a provider is added there.
 */
class Embed implements BlockViewFilterInterface
{
    public function filter(array $data): array
    {
        $src = trim((string)($data['src'] ?? ''));
        if($src === '') {
            return $data;
        }
        $data['embedSrc'] = $this->resolve($src);

        return $data;
    }

    private function resolve(string $url): ?string
    {
        foreach(['youtube', 'vimeo', 'spotify', 'googlemaps'] as $provider) {
            $resolved = $this->{$provider}($url);
            if($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function youtube(string $url): ?string
    {
        if(!preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $match)) {
            return null;
        }

        return 'https://www.youtube.com/embed/' . $match[1];
    }

    private function vimeo(string $url): ?string
    {
        if(!preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $match)) {
            return null;
        }

        return 'https://player.vimeo.com/video/' . $match[1];
    }

    private function spotify(string $url): ?string
    {
        if(!preg_match('~open\.spotify\.com/(track|album|playlist|episode|show)/(\w+)~', $url, $match)) {
            return null;
        }

        return sprintf('https://open.spotify.com/embed/%s/%s', $match[1], $match[2]);
    }

    private function googlemaps(string $url): ?string
    {
        if(!preg_match('~google\.[a-z.]+/maps~', $url)) {
            return null;
        }
        if(preg_match('~@(-?\d+\.\d+),(-?\d+\.\d+)~', $url, $coords)) {
            return sprintf('https://maps.google.com/maps?q=%s,%s&z=15&output=embed', $coords[1], $coords[2]);
        }
        if(preg_match('~/place/([^/@]+)~', $url, $place)) {
            return 'https://maps.google.com/maps?q=' . rawurlencode(rawurldecode($place[1])) . '&output=embed';
        }

        return 'https://maps.google.com/maps?q=' . rawurlencode($url) . '&output=embed';
    }
}
