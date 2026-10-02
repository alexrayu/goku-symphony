# Brief: manga/comics platform — vertical slice

## What this is

A self-hosted manga/comics reading platform, built in Symfony. It has two jobs, and both
shape how you work with me:

1. **Portfolio artifact.** Proof that I can build a real Symfony application outside Drupal —
   a real domain model, an async pipeline, a non-trivial reader.
2. **Learning vehicle.** I'm a senior Drupal developer learning Symfony and Doctrine. I know
   PHP, Twig and Symfony components as Drupal uses them. Doctrine, standalone Symfony
   architecture, Messenger and Security are new to me.
3. **Distributable product, later.** After the slice is live, it gets ported so other artists
   can download it from GitHub and install it on shared hosting with a web installer. Not
   built now, but the slice must not make that port a rewrite. See "Portability".

**Single-artist install.** One install = one artist's site. Invite-only posting (the artist
and invited collaborators), public reading. I'm a manga artist, so I'm also the first user.

## Reference stack — decided, do not relitigate

This is the full-featured stack the slice is built on. Shared-hosting fallbacks come later,
as additional adapters, not as replacements.

- **Symfony 7.4 LTS**, current PHP 8.x supported by it
- **Doctrine ORM**, **PostgreSQL 16**
- **Symfony Messenger** with **Redis** transport for async work
- **libvips** for image processing — not ImageMagick, not GD. Memory on a 2 GB droplet is the
  reason. Propose how to call it (php-vips via FFI vs. shelling out to `vipsthumbnail`) with
  the tradeoff, and let me choose.
- **WebP derivatives only.** No AVIF — too CPU-heavy on one shared vCPU.
- **Flysystem** with S3-compatible object storage — MinIO locally, S3-compatible in prod.
  From day one; no local-disk shortcut.
- **Twig + Turbo + Stimulus** via Symfony UX and **AssetMapper**. No Node build step, no React.
- **EasyAdmin** for backoffice
- **PHPStan level 8** with `phpstan/phpstan-doctrine`, **PHPUnit + Foundry**
- **Docker Compose** for local dev
- **Production: native Ubuntu LTS packages on a DigitalOcean 2 GB droplet, provisioned with
  Ansible.** No Docker in production. A single `versions.env` is read by both the Dockerfile
  and the Ansible playbook to keep dev and prod aligned.
- Search later: PostgreSQL full-text search (tsvector). No Meilisearch, no Solr.

If a decision genuinely blocks you, stop and ask. Don't substitute.

## Working agreement — teaching mode

This matters more than speed.

- **Propose, then build.** Before each step, say in a few lines what you're about to do and why.
- **Explain Doctrine decisions explicitly** — owning vs. inverse side, cascade, orphan removal,
  fetch modes, flush timing. Name the UnitOfWork behaviour involved. Assume I don't know it.
- **Query-count discipline.** For any page or repository method that loads related data, ask me
  to predict the number of queries before showing the profiler result. Fix N+1s deliberately,
  and explain the fix.
- **Where I'd write it differently in Drupal, say so.** Short comparisons help me map concepts.
- **Leave some work to me.** For each phase, mark one or two small pieces (a repository method,
  a Stimulus controller, a test) as "yours to write" and review what I produce instead of
  writing it yourself.
- Git from the first commit, small commits with one concern each. **I commit, not you:** at
  each logical boundary, stop, list the changed files and propose a commit message.
- Ask before adding any dependency not listed above.

## Domain model

- `Work` — `type: series | oneshot`. A oneshot is a Work with exactly one Chapter, not a
  separate entity. It simply renders without a chapter list.
- `Chapter` — belongs to a Work; number, title, reading direction (`ltr | rtl | vertical`),
  published flag.
- `Page` — belongs to a Chapter; `position` as gapped integers (10, 20, 30…) so inserting a
  page never rewrites the chapter; original object key; **width and height stored at ingest**;
  processing status.
- `User` — invite-only. No public registration. Users are created by a console command.
  Single-artist install: no ownership on `Work`, no tenancy. Every logged-in user can manage
  every Work.
- Reading progress (later, not in the slice): its own thin table
  `(user_id, chapter_id, last_page, updated_at)`, upserted, not a full entity.

## The vertical slice — the only goal right now

One complete path through every layer, live in production:

> log in → create a Work → create a Chapter → upload a ZIP/CBZ of pages → pages extracted and
> **natural-sorted** (`page_2` before `page_10`) → derivative jobs dispatched via Messenger →
> libvips produces WebP derivatives → stored in object storage → chapter published →
> public reader displays it

Reader in the slice: **vertical scroll only**, using stored dimensions to reserve space so
long strips don't shift. Paged LTR/RTL comes after the slice ships.

Ugly is fine. Live is the requirement.

## Phases — stop at each checkpoint

1. **Skeleton.** Symfony 7.4 project, Docker Compose (PHP, PostgreSQL 16, Redis, MinIO),
   PHPStan level 8 passing, PHPUnit running, `doctrine:schema:validate` in a make/composer
   script. **Checkpoint.**
2. **Domain + Doctrine.** Entities, migrations, Foundry factories, EasyAdmin for Work and
   Chapter. Teach the relationship mapping as you go. **Checkpoint.**
3. **Auth.** Login form, user-creation console command, access rules: only logged-in users
   create and upload; reading is public. **Checkpoint.**
4. **Ingestion pipeline.** ZIP/CBZ upload, extraction, natural sort, original upload to object
   storage, Messenger dispatch, libvips worker writing WebP derivatives and dimensions,
   per-page status. Include a test that runs the pipeline on a small fixture archive.
   **Checkpoint.**
5. **Reader.** Public chapter page, vertical scroll, a Stimulus controller for lazy loading
   with reserved dimensions. **Checkpoint.**
6. **Production.** Ansible playbook for the droplet: PHP-FPM, Nginx, PostgreSQL, Redis,
   libvips, a systemd unit for the Messenger worker, `versions.env`, deploy task, TLS behind
   Cloudflare (Full strict, origin certificate). **Checkpoint — slice is done when a stranger
   can open the reader URL.**

## Out of scope until the slice is live

Paged/RTL reader, reading progress, follows, search, comments, likes, ratings, collections,
notifications, API Platform, public registration, AVIF, multiple derivative sizes beyond what
the reader needs, multi-artist/tenancy, the shared-hosting port and installer. If something
feels necessary, list it in a `LATER.md` instead of building it.

Seed `LATER.md` in phase 1 with the shared-hosting port:
- Image processing fallbacks: Imagick, then GD (with a size/`memory_limit` guard for long strips)
- Doctrine Messenger transport + cron-driven `messenger:consume --time-limit`
- Local-disk Flysystem adapter, files served through a controller
- MariaDB/MySQL support: separate migration baseline, CI on both databases
- Chunked upload (shared hosts cap upload size and request time)
- Web installer: requirements check, DB credentials, admin user, writes `.env.local`, runs
  migrations, locks itself after success
- Release zip with `vendor/` and compiled assets; root `.htaccess` rewrite into `public/` for
  hosts that can't change the docroot
- A "shared" Docker Compose profile (php-apache, MariaDB, no Redis, no vips, low limits) to
  test the fallbacks
- License choice

## Constraints worth knowing

- 2 GB droplet, one shared vCPU. Messenger worker concurrency of 1; bound memory per job.
- Never load a whole page image into PHP memory if libvips can stream it.
- Secrets via environment / Symfony secrets — nothing secret in the repo.
- The production hostname is a subdomain of my domain, pointed at the droplet with a
  Cloudflare DNS record. Leave it as a `TODO` variable in Ansible for now.

## Portability — design for a later port and reworks

The slice is built for the reference stack only, but keep it cheap to port to shared
hosting and cheap to rework. Use seams only where a port is already known; no speculative
abstraction, no plugin system. Swapping an implementation means a new service plus a config
alias (Symfony DI), not changes to callers. Explain each seam when you introduce it, and how
it compares to Drupal's swappable services.

- **Image processing behind an `ImageProcessor` interface.** Only the libvips implementation
  now. Imagick/GD come later without touching the pipeline.
- **No PostgreSQL-only features in the slice.** No JSONB, array columns or custom types in
  entities. Portable Doctrine types only. (tsvector search is post-slice anyway.)
- **Image URLs come from one URL-generator service.** Never build storage/S3 URLs in Twig or
  controllers, so local-disk serving can plug in later.
- **Ingest starts from an archive already in storage, then dispatches a job.** How the file
  gets there (a single upload now, chunked upload later) stays swappable.
- **Infrastructure via env DSNs.** Database, Messenger transport and storage are selected by
  environment variables, never hardcoded.
- **Thin controllers and EasyAdmin CRUD.** Domain logic lives in services, so a UI rework or
  a different admin touches one layer.
- **No hard dependency on prod-only tooling in app code.** Ansible, systemd and Nginx
  configure the host; the app must not assume they exist.

## CLAUDE.md

After phase 1, write a short `CLAUDE.md` at the repo root capturing the stack decisions and
their reasons, the teaching-mode working agreement, the domain model rules (gapped positions,
oneshot-as-Work, dimensions at ingest, natural sort, single-artist), the portability rules,
and the out-of-scope list. Also state that branch memories for this project live in
`.claude/memories/<branch>.md` (committed, public repo), not in `~/.claude/tickets`. It exists so a
future session doesn't re-derive any of this or quietly swap libvips for ImageMagick.

---

Start with **phase 1 only**. Before writing anything, confirm the plan back to me in a few
lines and flag anything in this brief you think is wrong — once — with the tradeoff.
