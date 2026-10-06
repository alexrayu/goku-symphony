<?php

declare(strict_types=1);

namespace App\Media;

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
}
