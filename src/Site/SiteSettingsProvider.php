<?php

declare(strict_types=1);

namespace App\Site;

use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

// Settings for rendering, read on every page: cached in cache.app until the admin saves them.
// Before the first save there is no row; SITE_NAME / SITE_DESCRIPTION supply the defaults.
final class SiteSettingsProvider
{
    private const KEY = 'app.site_settings';

    private ?SiteSettings $settings = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        #[Autowire('%env(SITE_NAME)%')]
        private readonly string $defaultName,
        #[Autowire('%env(SITE_DESCRIPTION)%')]
        private readonly string $defaultTagline,
    ) {
    }

    // A detached copy for display. Admin edits go through find() instead.
    public function get(): SiteSettings
    {
        return $this->settings ??= $this->cache->get(self::KEY, fn (): SiteSettings => $this->find() ?? $this->defaults());
    }

    public function find(): ?SiteSettings
    {
        return $this->em->find(SiteSettings::class, 1);
    }

    public function defaults(): SiteSettings
    {
        return new SiteSettings($this->defaultName, $this->defaultTagline);
    }

    public function invalidate(): void
    {
        $this->settings = null;
        $this->cache->delete(self::KEY);
    }
}
