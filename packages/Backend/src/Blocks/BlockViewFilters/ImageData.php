<?php

namespace Solidarity\Backend\Blocks\BlockViewFilters;

use Skeletor\ContentEditor\Contracts\BlockViewFilterInterface;
use Skeletor\Core\Service\Contracts\CrudServiceInterface;

/**
 * Fills in the image details a section block's templates read but no longer store.
 *
 * The old editor resolved images when the block was saved: its parsers called the image
 * service and wrote `alt` (and a fresh `filename`) into the block data. The new block filters
 * are pass-through, so that has to happen on the way out instead - which is the better place
 * for it anyway: an alt text corrected in the media library now reaches every page that shows
 * the image, rather than only the ones saved since.
 *
 * Configured with the keys a given block uses, because they differ per block: an image at the
 * top level (`imageId` -> `alt`, or Three Pillars' `imageDesktopId` -> `imageDesktopAlt`), or
 * one per entry in a list (`cards`, `segments`, `projects`).
 */
class ImageData implements BlockViewFilterInterface
{
    /**
     * @param array<string, string> $fields id key => alt key, on the block itself
     * @param array<string, array<string, string>> $lists list key => [id key => alt key], per entry
     */
    public function __construct(
        private CrudServiceInterface $imageService,
        private array $fields = [],
        private array $lists = []
    ) {

    }

    public function filter(array $data): array
    {
        foreach ($this->fields as $idKey => $altKey) {
            $data = $this->resolve($data, $idKey, $altKey);
        }

        foreach ($this->lists as $listKey => $keys) {
            if (empty($data[$listKey]) || !is_array($data[$listKey])) {
                continue;
            }
            foreach ($data[$listKey] as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                foreach ($keys as $idKey => $altKey) {
                    $item = $this->resolve($item, $idKey, $altKey);
                }
                $data[$listKey][$index] = $item;
            }
        }

        return $data;
    }

    /**
     * The filename is refreshed too, so replacing the file behind a media entry updates the
     * pages using it. A missing or deleted image leaves both keys alone: the stored filename is
     * still the best guess the template has, and an empty alt is correct for one that is gone.
     */
    private function resolve(array $data, string $idKey, string $altKey): array
    {
        $data[$altKey] = $data[$altKey] ?? '';
        if (empty($data[$idKey])) {
            return $data;
        }

        try {
            $image = $this->imageService->getById($data[$idKey]);
        } catch (\Throwable $e) {
            return $data;
        }
        if (!$image) {
            return $data;
        }

        $data[$altKey] = $image->alt ?? '';
        if (!empty($image->filename)) {
            $data[$this->filenameKeyFor($idKey)] = $image->filename;
        }

        return $data;
    }

    /** `imageId` stores its file under `filename`; `imageDesktopId` under `imageDesktopFilename`. */
    private function filenameKeyFor(string $idKey): string
    {
        $base = preg_replace('/Id$/', '', $idKey);

        return $base === 'image' ? 'filename' : $base . 'Filename';
    }
}
