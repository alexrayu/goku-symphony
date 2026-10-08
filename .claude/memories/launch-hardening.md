# launch-hardening

## Status
- 2026-10-08: audit item 1 (before launch) done, uncommitted. Report: `~/Documents/tickets/goku-symfony/audit-2026-10-08/audit.md`. 32 tests + PHPStan green.
- Not verified behind real Nginx/Cloudflare: media 304 through X-Accel and Cloudflare honouring `s-maxage` on images. Check in the phase-6 container or on the droplet.

## Decisions
- Unpublish retraction: user chose short edge TTL (media `s-maxage=3600` + Last-Modified 304) over a Cloudflare purge API. New URLs alone do not retract: the old URLs stay cached at the edge.
- Security headers come from a Symfony listener, not Nginx (app must not assume Nginx). Only `frame-ancestors` CSP for now; a full CSP needs nonces for inline styles, JSON-LD and importmap.
- Installer window closed operationally: README says create the first user with `app:user:create` over SSH before the DNS record exists.

## Gotchas
- `VIPS_BLOCK_UNTRUSTED=1` blocks `vipsload` (.v) and `matload`, which the scramble pipeline uses for its own intermediates. Set it only on calls that read the uploaded file. Dev libvips also has `magickload` (ImageMagick), which made blocking worthwhile.
- `BinaryFileResponse::prepare()` returns early for 304, so no X-Accel-Redirect or body is sent.

## Next (audit order)
- 2: draft preview, page images in admin detail, delete-pages action with storage cleanup, oneshot guard, hide `direction`.
