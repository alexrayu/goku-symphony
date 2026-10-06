# CLAUDE.md

Self-hosted manga/comics platform in Symfony. Portfolio piece and learning vehicle for a senior Drupal developer new to Doctrine, Messenger and Security. One install = one artist's site; invite-only posting, public reading. Full brief: `.claude/manga-platform-brief.md`.

Branch memories live in `.claude/memories/<branch>.md` (committed, public repo), not in `~/.claude/tickets`. Read the current branch's file at session start.

## Stack (decided, do not relitigate; if one blocks you, stop and ask)

- Symfony 7.4 LTS, Doctrine ORM, PostgreSQL 16. Keep all `symfony/*` at 7.4 (recheck the lock after any `composer require`).
- Messenger with Redis transport; worker concurrency 1.
- libvips for images. Never ImageMagick or GD in the slice: memory on a 2 GB droplet is the reason. Never load a whole page image into PHP memory if libvips can stream it.
- WebP derivatives only. No AVIF (CPU cost on one shared vCPU).
- Flysystem local adapter on a protected dir outside the web root (`STORAGE_PATH`). Originals never web-reachable; derivatives public via a controller (`X-Accel-Redirect` in prod). No S3/MinIO.
- Twig + Turbo + Stimulus via Symfony UX and AssetMapper. No Node build, no React.
- EasyAdmin for backoffice. PHPStan level 8 (+ doctrine), PHPUnit + Foundry.
- Dev: Docker Compose (`php -S`, no FPM/Nginx until phase 6). Prod: native Ubuntu packages on a DigitalOcean 2 GB droplet via Ansible, no Docker. `versions.env` is shared by Dockerfile and Ansible.
- Search later: PostgreSQL tsvector. No Meilisearch/Solr.
- Ask before adding any dependency not listed here.

## Working agreement: build mode now, teaching mode later

- Since 2026-10-06 (from phase 6): build without teaching stops. No query-count predictions, no "yours to write" pieces, no per-step proposals. Still ask on genuine blockers and dependency additions; still never commit (list files and messages per concern when a phase is done).
- When the user asks to be taught, apply the teaching rules below to the finished code.

### Teaching mode (paused)

- Propose, then build: a few lines on what and why before each step.
- Explain Doctrine decisions explicitly (owning vs. inverse side, cascade, orphan removal, fetch modes, flush timing), naming the UnitOfWork behaviour.
- Query-count discipline: for any page or repository method loading related data, ask the user to predict the query count before showing the profiler. Fix N+1s deliberately and explain.
- Note where Drupal would do it differently.
- Per phase, mark one or two small pieces as "yours to write" (repository method, Stimulus controller, test) and review them instead of writing them.
- The user commits, never Claude. At each logical boundary stop, list changed files, propose a message. Small commits, one concern each.
- Stop at each phase checkpoint.

## Domain rules

- `Work.type`: `series | oneshot`. A oneshot is a Work with exactly one Chapter, rendered without a chapter list.
- `Chapter`: number, title, reading direction (`ltr | rtl | vertical`), published flag.
- `Page.position`: gapped integers (10, 20, 30...) so inserts never rewrite the chapter.
- Page width and height are stored at ingest; the reader reserves space from them.
- Archive page order is natural-sorted (`page_2` before `page_10`).
- Single-artist: no ownership on `Work`, no tenancy; every logged-in user manages every Work. No public registration; users are created by console command.
- Reading progress (later): thin table `(user_id, chapter_id, last_page, updated_at)`, upserted, not an entity.

## Portability rules

Seams only where a port is already known; no speculative abstraction. Swapping = new service + DI alias, not caller changes. Explain each seam when introduced, with the Drupal comparison.

- Image processing behind an `ImageProcessor` interface; libvips only for now.
- Portable Doctrine types only: no JSONB, array columns, custom types.
- All image URLs from one URL-generator service; never build storage URLs in Twig or controllers.
- Ingest starts from an archive already in storage, then dispatches a job.
- Database, Messenger transport and storage chosen by env DSNs, never hardcoded.
- Thin controllers and EasyAdmin CRUD; domain logic in services.
- App code must not assume Ansible, systemd or Nginx.
- Secrets via env / Symfony secrets; nothing secret in the repo.

## Out of scope until the slice is live

Paged/RTL reader, reading progress, follows, search, comments, likes, ratings, collections, notifications, API Platform, public registration, AVIF, extra derivative sizes, multi-artist/tenancy, shared-hosting port and installer. If something feels necessary, add it to `LATER.md`.

## Commands

`make up | down | install | stan | schema | test` (run inside the php container; use `--no-deps` for one-off runs).

## Gotchas

- Composer's global GitHub token may be stale (Flex recipe 404s); use a clean `COMPOSER_HOME`.
- Do not set `config.platform.php` below the container's PHP patch version.
- `versions.env` is passed via `--env-file`; Compose's default `.env` is Symfony's.
