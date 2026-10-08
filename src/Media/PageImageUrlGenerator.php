<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Entity\SiteSettings;
use App\Entity\Work;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

// The only place page image URLs are built (Twig: page_image_url(page)).
// Moving images to a CDN or another host changes this class, not the templates.
final class PageImageUrlGenerator
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[AsTwigFunction('page_image_url')]
    public function derivative(Page $page): string
    {
        return $this->urlGenerator->generate('media_page', ['id' => $page->getId()]);
    }

    #[AsTwigFunction('chapter_thumb_url')]
    public function thumbnail(Chapter $chapter): string
    {
        return $this->urlGenerator->generate('media_thumb', ['id' => $chapter->getId()]);
    }

    // The work's chosen cover, else the first chapter's.
    #[AsTwigFunction('work_thumb_url')]
    public function workThumbnail(Work $work, Chapter $first): string
    {
        return null === $work->getCoverVersion()
            ? $this->thumbnail($first)
            : $this->urlGenerator->generate('media_work_thumb', ['id' => $work->getId(), 'version' => $work->getCoverVersion()]);
    }

    #[AsTwigFunction('work_cover_url')]
    public function workCover(Work $work, Chapter $first): string
    {
        return null === $work->getCoverVersion()
            ? $this->cover($first)
            : $this->urlGenerator->generate('media_work_cover', ['id' => $work->getId(), 'version' => $work->getCoverVersion()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[AsTwigFunction('site_logo_url')]
    public function logo(SiteSettings $settings): ?string
    {
        return null === $settings->getLogoVersion() ? null : $this->urlGenerator->generate('media_logo', ['version' => $settings->getLogoVersion()]);
    }

    // Absolute: link-preview crawlers resolve og:image without a base URL.
    #[AsTwigFunction('chapter_cover_url')]
    public function cover(Chapter $chapter): string
    {
        return $this->urlGenerator->generate('media_cover', ['id' => $chapter->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
