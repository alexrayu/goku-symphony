# Later

Deferred until the vertical slice is live.

## Shared-hosting port

- Image processing fallbacks: Imagick, then GD (with a size/`memory_limit` guard for long strips)
- Doctrine Messenger transport + cron-driven `messenger:consume --time-limit`
- S3-compatible Flysystem adapter (optional offsite storage)
- MariaDB/MySQL support: separate migration baseline, CI on both databases
- Chunked upload (shared hosts cap upload size and request time)
- Web installer: requirements check, DB credentials, admin user, writes `.env.local`, runs migrations, locks itself after success
- Release zip with `vendor/` and compiled assets; root `.htaccess` rewrite into `public/` for hosts that can't change the docroot
- A "shared" Docker Compose profile (php-apache, MariaDB, no Redis, no vips, low limits) to test the fallbacks
- License choice
