<?php

declare(strict_types=1);

namespace App\Site;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

// `site` in every template: name, tagline, accent, logo, about content.
final class SiteTwigGlobals extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly SiteSettingsProvider $settings)
    {
    }

    public function getGlobals(): array
    {
        return ['site' => $this->settings->get()];
    }
}
