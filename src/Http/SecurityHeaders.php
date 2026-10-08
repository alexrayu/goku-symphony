<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

// Baseline hardening on every response, errors and media included. Set by the app, so it holds
// whatever front server runs it; a header already present (front server, controller) wins.
final class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // For browsers without CSP frame-ancestors.
        'X-Frame-Options' => 'DENY',
    ];
    private const HSTS_MAX_AGE = 31536000;
    // Inline blocks are allowed by hash, not nonce: the HTML stays identical between requests, so
    // ETag revalidation and the shared cache keep working. style attributes (reserved page sizes,
    // EasyAdmin) are allowed; injected CSS cannot run script.
    private const POLICY = "default-src 'self'; script-src 'self'%s; style-src 'self'%s; style-src-attr 'unsafe-inline'; "
        ."img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'";
    private const INLINE_BLOCK = '#<(script|style)\b([^>]*)>(.*?)</\1\s*>#is';

    // Before PublicPageCache (0): a 304 has no body to hash, and browsers merge its headers into
    // the cached page, so the policy must come from the full response.
    #[AsEventListener(priority: 16)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;
        foreach (self::HEADERS as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy($response));
        }
        // Only over HTTPS: a browser ignores it on plain HTTP, and dev runs without TLS.
        if ($event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', sprintf('max-age=%d', self::HSTS_MAX_AGE));
        }
    }

    private function policy(Response $response): string
    {
        $hashes = ['script' => [], 'style' => []];
        $content = $response instanceof BinaryFileResponse || $response instanceof StreamedResponse ? false : $response->getContent();
        if (\is_string($content) && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            preg_match_all(self::INLINE_BLOCK, $content, $blocks, \PREG_SET_ORDER);
            foreach ($blocks as [, $tag, $attributes, $body]) {
                // External scripts are covered by 'self'; data blocks (JSON-LD) are never executed.
                if (preg_match('#\bsrc\s*=|type\s*=\s*["\']?application/(ld\+)?json#i', $attributes)) {
                    continue;
                }
                $hashes[strtolower($tag)][] = sprintf(" 'sha256-%s'", base64_encode(hash('sha256', $body, true)));
            }
        }

        return sprintf(self::POLICY, implode('', array_unique($hashes['script'])), implode('', array_unique($hashes['style'])));
    }
}
