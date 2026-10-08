<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

// Baseline hardening on every response, errors and media included. Set by the app, so it holds
// whatever front server runs it; a header already present (front server, controller) wins.
final class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // No page is meant to be framed: stops clickjacking of /admin. The CSP form for current
        // browsers, X-Frame-Options for old ones.
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Frame-Options' => 'DENY',
    ];
    private const HSTS_MAX_AGE = 31536000;

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
        // Only over HTTPS: a browser ignores it on plain HTTP, and dev runs without TLS.
        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', sprintf('max-age=%d', self::HSTS_MAX_AGE));
        }
    }
}
