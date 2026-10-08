<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

// Anonymous visitors get shared-cacheable public pages (Cloudflare holds them for 5 minutes),
// browsers revalidate every time and get 304 on an unchanged ETag. Requests that carry a session
// stay private: Symfony's session listener (priority -1000) runs later and overrides these headers
// whenever the session was used.
final class PublicPageCache
{
    private const ROUTES = ['home', 'work_show', 'chapter_read', 'about', 'robots', 'sitemap', 'favicon'];
    private const SHARED_MAX_AGE = 300;

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$request->isMethodCacheable() || 200 !== $response->getStatusCode()
            || !\in_array($request->attributes->get('_route'), self::ROUTES, true) || $request->hasPreviousSession()) {
            return;
        }

        $response->setPublic()->setMaxAge(0)->setSharedMaxAge(self::SHARED_MAX_AGE);
        $response->headers->addCacheControlDirective('stale-while-revalidate', '60');
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);
    }
}
