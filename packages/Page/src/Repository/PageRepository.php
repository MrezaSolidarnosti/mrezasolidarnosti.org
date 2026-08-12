<?php

namespace Solidarity\Page\Repository;

use Solidarity\Page\Entity\Page;
use Solidarity\Page\Factory\PageFactory;

class PageRepository extends \Skeletor\Page\Repository\PageRepository
{
    const ENTITY = Page::class;
    const FACTORY = PageFactory::class;

    const STATUS_PUBLISHED = 1;

    public function getSearchableColumns(): array
    {
        return ['a.title', 'a.slug'];
    }

    /** A published page identified by its slug within a given locale. */
    public function findPublishedBySlugAndLocale(string $slug, string $localeCode): ?Page
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(static::ENTITY, 'p')
            ->where('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->andWhere('p.languageCode = :code')
            ->setParameter('slug', $slug)
            ->setParameter('status', self::STATUS_PUBLISHED)
            ->setParameter('code', $localeCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** The published homepage for a given locale. */
    public function findPublishedHomeByLocale(string $localeCode): ?Page
    {
        return $this->findPublishedBySlugAndLocale('homepage', $localeCode);
    }

    /**
     * [localeCode => slug] for every published page sharing a translation group.
     * Drives the language switcher so each locale links to its own localized slug.
     */
    public function getLocalizedSlugs(?int $translationGroupId): array
    {
        if ($translationGroupId === null) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.languageCode AS code', 'p.slug AS slug')
            ->from(static::ENTITY, 'p')
            ->where('p.translationGroupId = :gid')
            ->andWhere('p.status = :status')
            ->setParameter('gid', $translationGroupId)
            ->setParameter('status', self::STATUS_PUBLISHED)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['code'] !== null) {
                $map[$row['code']] = $row['slug'];
            }
        }

        return $map;
    }

    /**
     * The published slug of the $targetLocale variant of the page identified by
     * $sourceSlug in $sourceLocale, or null when the source page or its translation
     * doesn't exist. Drives localizeUrl(): a default-locale menu slug (e.g. sr
     * `doniraj`) resolves to its localized counterpart (e.g. en `donate`).
     */
    public function findTranslatedSlug(string $sourceSlug, string $sourceLocale, string $targetLocale): ?string
    {
        $source = $this->findPublishedBySlugAndLocale($sourceSlug, $sourceLocale);
        if (!$source) {
            return null;
        }
        // Translations point at the source page's id via translationGroupId; the
        // source itself may carry a group id or be the group root (its own id).
        $groupId = $source->translationGroupId ?? $source->getId();

        $row = $this->entityManager->createQueryBuilder()
            ->select('p.slug AS slug')
            ->from(static::ENTITY, 'p')
            ->where('p.languageCode = :code')
            ->andWhere('p.status = :status')
            ->andWhere('(p.translationGroupId = :gid OR p.id = :gid)')
            ->setParameter('code', $targetLocale)
            ->setParameter('status', self::STATUS_PUBLISHED)
            ->setParameter('gid', $groupId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row['slug'] ?? null;
    }

    public function createTranslation(int $pageId): ?Page
    {
        $source = $this->entityManager->find(static::ENTITY, $pageId);
        if (!$source) {
            return null;
        }

        $translation = new Page();
        $translation->title = $source->title;
        $translation->description = $source->description;
        $translation->slug = $source->slug;
        $translation->blockData = $source->blockData;
        $translation->featuredImage = $source->featuredImage;
        $translation->status = $source->status;
        $translation->isLoginProtected = $source->isLoginProtected;
        $translation->seoTitle = $source->seoTitle;
        $translation->seoDescription = $source->seoDescription;
        $translation->seoImage = $source->seoImage;

        $translation->languageCode = 'en';

        // Both pages end up in the same group, and an existing one wins.
        //
        // The translation used to take the source's *id* as its group while the source kept
        // a NULL one, so the two were never in the same group: the language switcher calls
        // getLocalizedSlugs($page->translationGroupId), which found nothing from the source
        // and only itself from the translation. A translated pair could not link to each
        // other in either direction and both fell back to the homepage. (PageActionTest
        // passed throughout because its fixtures give both pages one group id by hand —
        // which is the shape this now actually produces.)
        //
        // Reusing the source's existing group rather than overwriting it matters once a
        // third locale appears: stamping $pageId unconditionally would split the group.
        $groupId = $source->translationGroupId ?? $pageId;
        $translation->translationGroupId = $groupId;
        $source->translationGroupId = $groupId;

        $this->entityManager->persist($translation);
        $this->entityManager->flush();

        return $translation;
    }
}