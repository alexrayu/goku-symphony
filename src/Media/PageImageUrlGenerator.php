<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Chapter;
use App\Entity\Page;
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

    // Absolute: link-preview crawlers resolve og:image without a base URL.
    #[AsTwigFunction('chapter_cover_url')]
    public function cover(Chapter $chapter): string
    {
        return $this->urlGenerator->generate('media_cover', ['id' => $chapter->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
