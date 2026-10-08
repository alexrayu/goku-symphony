# main (project memory)

## Status
Phases 1-3 committed (skeleton, domain + EasyAdmin, auth + first-run installer).

Phase 4 (ingestion) committed (a033e81). PHPStan level 8 clean. Verified end to end in headless Chromium with a live worker: upload a ZIP on a Chapter, worker extracts, natural order, WebP derivatives, all pages `ready`. All tests pass.

Phase 5 (reader) built, uncommitted, verified in headless Chromium (anonymous): `/` lists works with published chapters, `/w/{slug}` lists chapters (oneshot: reader in place), `/w/{slug}/{number}` reader, `/media/page/{id}.webp` streams derivatives. 21 tests pass, PHPStan clean. Lazy loading is native `loading="lazy"` (user said continue without picking; recommended option), no Stimulus/AssetMapper yet. Phase 5 checkpoint closed 2026-10-06: oneshot extra query left as is (recommended option). User commits phase 5.

Dev DB demo content (2026-10-08): 3 series (neon-tide 4 ch, paper-lanterns 3, ashfall 2) + 2 oneshots (the-last-train, garden-of-small-gods), all published, generated placeholder art. Old "E2E Test Work" removed (pre-scramble pages, rendered as "could not load"). Regenerate: `~/Documents/tickets/goku-symfony/ui-polish/` gen_pages.py (SVG via Chromium, zips) + upload.py (admin upload with a temp `demo-seed@goku.test` user, deleted after; works/chapters inserted by SQL, publish by SQL, worker via `messenger:consume async`).

User-owned pieces left unwritten on purpose: `make check` target, smoke test in `tests/`, page position-gap helper test, phase 3 login functional test (UserFactory password is "!", so use `loginUser()` or add a hashed default). `ArchivePageOrder::sort()` + its test were meant for the user, but they asked Claude to write them and will study them later.

Open question to the user: trim the `LATER.md` web-installer line to what is left (requirements check, DB credentials, `.env.local`, migrations).

## Working mode
- 2026-10-06: user switched to build mode (see CLAUDE.md). Build phase 6 without teaching stops; the user studies the code and asks for teaching later.

## Phase 6 decisions (2026-10-06)
- Droplet Ubuntu 24.04; PHP 8.4 from ondrej PPA (8.3 rejected: 26 locked packages need 8.4). PG 16, Redis 7 from distro.
- Deploy = git clone of public GitHub repo (`alexrayu/goku-symphony`) into releases/, symlink switch.
- Secrets in untracked `ansible/secrets.yml` + committed example; no vault.
- No droplet yet: verify against a local Ubuntu 24.04 systemd container. Harness (Dockerfile, inventory, key, self-signed cert, Playwright venv, e2e.py) in `~/Documents/tickets/goku-symfony/phase-6/`; container `goku-prodtest`, ports 2222/8443, host `goku.test`. Deploy test: `docker cp` a bare clone to `/opt/goku.git` (chown goku), `-e app_repo=file:///opt/goku.git -e app_ref=main`. Test secrets go in gitignored `ansible/secrets.yml`; delete after.
- Status 2026-10-06: phase 6 done locally. Provision idempotent; two deploys (release switch, worker restarts into new release) and full slice E2E pass in the container. Container removed, image `goku-prodtest` kept; test `ansible/secrets.yml` deleted. Next: user creates the droplet, fills inventory host, `app_hostname`, real `secrets.yml` (origin cert), runs `make provision` and `make deploy REF=...`; slice done when a stranger opens the reader URL.
- MediaController uses BinaryFileResponse on `STORAGE_PATH` (not Flysystem) so `SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER=1` + Nginx `X-Accel-Mapping` hand the body to Nginx.
- Cloudflare Free caps request bodies at 100 MB; upload limit stays 200M (origin only). Told user.

## SEO + protection work (started 2026-10-06, build mode)
- Decisions: tile scrambling like goku-static (128px tiles, `order[source]=slot`) plus an 8px gutter of real neighbours per tile (144px cells). No gutter seamed gradients in the browser (border err 8.5 vs 1.1 interior); 4px left traces (2.6); 8px clean (1.16 vs 1.09), ~+25% bytes. The first no-gutter test used text on white and hid the seams: test seams on gradients, in the real browser; friction (contextmenu/drag/select blocked); URLs `/{slug}` + `/{slug}/chapter-{n}`, reserved slugs validated, oneshot chapter URL 301 to `/{slug}`; site meta via env `SITE_NAME`/`SITE_DESCRIPTION`; anonymous HTML `public, s-maxage=300` + ETag, Cloudflare Cache Rule documented, no purge API.
- Scrambling via vips `mapim` with an index built from .mat matrices (8 vips calls; 0.6s normal page, 2.5s 14k strip). Per-tile extract_area was 4.5s/35s.
- Reading height cap 14464 (113 tile rows x 144px cells <= 16383).
- og:image = 1200x630 JPEG cover from each chapter's first page, under /media/cover/ (robots allows), pages under /media/page/ disallowed.
- Canvas segments <= 4096px tall (iOS canvas area limit).
- Status 2026-10-06: built (scramble at ingest, covers keyed by chapter, Stimulus reader, clean URLs, meta/JSON-LD, robots/sitemap, PublicPageCache listener, Nginx assets/gzip, ansible/README.md). 28 tests + PHPStan green. Container E2E passed 2026-10-06 with the 8px gutter (canvas vs original mean diff 1.1-2.4, contextmenu blocked, no <img>, headers/304/robots/sitemap/JSON-LD ok). Lighthouse mobile: home and work 100/100/100/100, reader 99 (LCP 2.0s, CLS 0, TBT 20ms); local container, self-signed cert, no Cloudflare. Pending: user commits gutter change; prod droplet. Dev DB writes (user/work inserts) were permission-denied: verify on the container instead.
- Lighthouse harness in phase-6/lh (npm lighthouse 12 on Node 18.19 works). Chrome needs `--no-sandbox` (AppArmor userns block). Series `/scramble-test` is the chapter list; the reader is `/scramble-test/chapter-1`.
- importmap only on the reader page; other public pages ship no JS. Recipe demo controllers (hello, csrf_protection) removed: login uses session CSRF tokens.
- Container-created files are root-owned on the host (uid 1001 = ara): chown after composer/recipes/migrations:diff.

## Gotchas
- Composer's global GitHub token is stale; Flex recipe fetch 404s with it. Require with a clean `COMPOSER_HOME` (`-e COMPOSER_HOME=/tmp/ch`), and quote version constraints in zsh.
- `doctrine-bundle` pulls Symfony 8.x components. Keep all `symfony/*` at 7.4; recheck the lock after any `composer require`.
- Do not set `config.platform.php` below the container's PHP patch version.
- `versions.env` is passed with `--env-file`; Compose's default `.env` is Symfony's.
- Repo `.gitignore` is Symfony's; the original ignored `.claude`, which would exclude these memories.
- `tests/bootstrap.php` lost the recipe's `method_exists` guard because level 8 flags it.
- zsh does not word-split `$VAR` holding a command; use a shell function (`dc(){ docker compose --env-file versions.env "$@"; }`).
- `csrf.yaml`: only `submit` is stateless. Stateless tokens need the Stimulus csrf controller (no `assets/` yet); login broke with "Invalid CSRF token" while `authenticate` was listed. Custom admin forms use session token ids (e.g. `chapter_upload`).
- Installer subscriber runs at priority 64: the router (32) 404s `/` before a later listener, and the firewall (8) would send `/admin` to `/login` first.
- Functional tests of public pages need a user in the DB, or they redirect to `/install`. `InstallState` caches only "installed"; `cache:clear` does NOT clear cache.app, use `cache:pool:clear cache.app`.
- Flysystem is autowired only by name (`FilesystemOperator $defaultStorage`); in tests fetch `'default.storage'`. Test env writes to `var/storage-test` (`.env.test`).
- Foundry 2 factories return real entities; no `_real()`.
- Tests using the in-memory transport across requests need `$client->disableReboot()`.
- EasyAdmin `NumberField` on a DECIMAL (string) needs `setStoredAsString()` plus `setNumberFormat()`; the Intl formatter only accepts int|float. Chapter "new" also crashed on an unmanaged placeholder Work; now preselects the newest Work (409 if none) with number "1".
- EasyAdmin wraps a `formatValue()` string in Twig Markup (rendered raw): never return user text from it. Its null badge is picked when the formatted value is null; blank nulls come from overriding `label/null` on the dashboard (2026-10-08, with public chapter-number labels and plural headings in admin lists). Direction column still shows the enum name ("Ltr"); readable labels offered, not done.
- `/admin` redirects to the Work list; tests expect `/admin` -> 302 `/admin/work`.
- `symfony/web-profiler-bundle` is NOT installed (no toolbar). Test env enables the core profiler (`framework.profiler.collect: false`); tests call `enableProfiler()`. The first request shares the kernel with Foundry, so reset `doctrine.debug_data_holder` and clear the EM before a counted request, or setup INSERTs count and initialized collections skip ORDER BY.
- Oneshot URL `/w/{slug}` costs one more (4 test / 3 dev): `findPublishedByWork()` then `findPublishedForReader()` re-read the same chapter; DQL bypasses the identity map. Dropping it needs care: `getOneOrNullResult()` throws if a oneshot has 2 published chapters (invariant not enforced yet), and `setMaxResults` on a fetch-joined collection truncates pages.
- Reader query count is 3 per request in tests: installed check (array cache in test; cached in dev/prod, so 2 there), work by slug, chapter + pages fetch join.
- Reader CSS uses `max-width: 100%` on pages, not `width: 100%`: narrow strips must not be upscaled.

- Flysystem permissions need `0o750` YAML octal (bare `0750` = string = decimal 750) plus `visibility: public`, or files keep the umask. Dev as root hid it; prod FPM could not mkdir. Test asserts modes.
- Test harness only: host `apparmor-profiles` php-fpm profile attaches inside privileged containers (blocks sd_notify, socket names); container runs FPM from a copied binary via drop-in. Ubuntu Docker images block service start on install (policy-rc.d) and lack sudo.
- FPM socket kept at default `php8.4-fpm.sock` (that AppArmor profile allows only `php*-fpm.sock`).
- `deb822_repository` for the ondrej PPA; `apt_repository` ppa: needs gnupg + deprecated apt-key.

## Decisions
- Dev PHP container runs `php -S` (no FPM/Nginx until phase 6). Uploads capped at 200M via `conf.d/uploads.ini` in the Dockerfile; prod Nginx needs matching `client_max_body_size`.
- Added beyond the brief, user-approved: `symfony/test-pack`, `phpstan-symfony`, `phpstan-phpunit`, `symfony/rate-limiter` (login throttling), `symfony/process` (vips CLI).
- Branching (2026-10-08): `phase-1-skeleton` renamed to `main`, the trunk and deploy default (`app_ref: main`). User rule: work directly on `main`, no feature branches (switching is distracting). The Stop hook does not nag on `main`, so update this file by hand at the end of each task.
- Phase 2: `Chapter.number` NUMERIC(6,1); `Work.slug` unique; no cascade on `Work.chapters`; no roles column, `getRoles()` constant; oneshot rule enforced in a service later.
- Installer (user-approved override of "installer out of scope" / "users by console only"): open form, first user only, no token (user removed it; install before a droplet is public). `UserProvisioner` is the one user-creation path.
- Phase 4: libvips via CLI + `symfony/process`, not FFI. One WebP, max 1200 wide, Q80, never upscaled, height capped at 16383 (WebP limit) so very tall strips get narrower (a 900x30000 strip becomes 491x16383); slicing strips is a later candidate. Stored dimensions are the derivative's. Re-upload to a chapter with pages is rejected. Originals and derivatives use generated keys (no entry names: no traversal); 64 MB uncompressed cap per entry. Final derivative failure sets page `failed` via a WorkerMessageFailedEvent listener. Derivative key is derived (`derivatives/{chapter}/{page}.webp`), no column.
- Deleting a chapter removes page rows (cascade) but not storage files; not handled yet.
- Phase 5: media controller checks published (or logged in) per image request: one PK query, so drafts are not guessable by id. Published images get `public, max-age=1y, immutable` (new upload = new page ids); drafts `private, no-store`. Phase 6 keeps the PHP check and swaps the body for `X-Accel-Redirect`. All image URLs come from `PageImageUrlGenerator` (Twig `page_image_url()`). Reader filters non-ready pages in PHP, not in the fetch-join WHERE (partial collection trap). `Chapter::getNumberLabel()` drops ".0" for URLs/headings.
- Browser testing: no Chrome MCP. Headless Chromium via Playwright in a Python venv in the scratchpad (system Node 18 too old for npm playwright; do not upgrade system Node). Cached browser at `~/.cache/ms-playwright/chromium-1208`. Run a worker with `messenger:consume async --time-limit=300` in the background.

## UI polish (2026-10-08, build mode)
- User found UI poor; chose: portrait thumb at ingest (approved exception to "no extra derivative sizes"), scope public + login/installer + EasyAdmin branding, generated demo data.
- Thumb 400x560 WebP (`thumbs/{chapter}.webp`, `/media/thumb/{id}.webp`) from the chapter's first page, same call path as the og cover; `ImageProcessor::toCoverJpeg` became `toCover`, format from the target extension. Chapters ingested before this have no thumb (broken card image): re-ingest.
- Home groups `findAllPublishedWithWork()` in the controller (first chapter = cover, last = latest); `WorkRepository::findPublished` removed. Series reader adds `findPublishedByWork` for prev/next: 4 queries in tests (was 3).
- Theme tokens + buttons in `templates/theme/_base.css.twig`, inlined by public and auth layouts; accent #ff6a4d with dark text on it (white on coral fails AA). EasyAdmin: `Theme::primaryColor` + zinc grays + dark default, logo in title (EA renders title raw, name escaped).
- Error page `templates/bundles/TwigBundle/Exception/error.html.twig`; preview in dev at `/_error/404`.
- Status: done, 29 tests + PHPStan green, screenshots checked desktop/mobile. Not rerun: Lighthouse. Uncommitted; user commits.
- Possible follow-ups (not done): reader width cap on desktop, home ordering by recency (no timestamps yet).

## Launch hardening (2026-10-08, audit item 1, committed 219780d)
- Audit report: `~/Documents/tickets/goku-symfony/audit-2026-10-08/audit.md`. Items 1-5 done, plus a full CSP. Open: verify behind real Nginx (phase-6 container or droplet; deploys clone the repo, so commit first): media 304 through X-Accel, CSP intact through Nginx, reader LCP preload. Cloudflare `s-maxage` only on the droplet.
- Unpublish retraction: user chose a short edge TTL (media `s-maxage=3600` + Last-Modified 304) over a Cloudflare purge API. New URLs alone do not retract: the old URLs stay cached at the edge. Not yet verified behind real Nginx/Cloudflare (304 through X-Accel, Cloudflare honouring `s-maxage` on images).
- Security headers come from the `SecurityHeaders` listener, not Nginx. Only the `frame-ancestors` CSP so far; a full CSP needs nonces for inline styles, JSON-LD and importmap.
- Installer window closed operationally: README says to create the first user with `app:user:create` over SSH before the DNS record exists.
- `VIPS_BLOCK_UNTRUSTED=1` also blocks `vipsload` (.v) and `matload`, which the scramble pipeline uses for its own intermediates: set it only on calls reading the uploaded file. Dev libvips has `magickload`.
- `BinaryFileResponse::prepare()` returns early for 304: no X-Accel-Redirect, no body.

## Artist workflow (2026-10-08, audit item 2, committed 90d1430)
- Draft preview: logged-in users read unpublished chapters (reader + chapter list, "Draft" badges, noindex). Gotcha: calling `getUser()` on the lazy firewall touches the session even for anonymous visitors, and Symfony then marks the response private, which killed edge caching on every public page (SeoTest caught it). `ReaderController::previewsDrafts()` checks `hasPreviousSession()` first.
- "Page images in admin" became a Preview action that opens the real reader in a new tab: pages are scrambled, so `<img>` thumbnails are impossible, and per-page unscrambled previews would be another derivative size.
- `ChapterPages` service: `clear()` (Delete pages action, confirm page + CSRF POST) and `deleteChapter()` (EasyAdmin `deleteEntity` override). Rows are deleted before files; both are refused while pages are pending/processing. Chapter delete also removes `incoming/{id}`.
- Oneshot rule: `Assert\Callback` on Chapter (second chapter on a oneshot) and Work (switching to oneshot with more than one chapter). Factories don't fill `Work::$chapters`; tests clear the EM and reload.
- `direction` field hidden in admin; LATER.md notes to restore it with the paged/RTL reader.

## Dates and summaries (2026-10-08, audit item 3, committed 3a26a46)
- `Chapter.publishedAt` is set by `setPublished(true)` only when null (first publication; kept through unpublish/republish), constructor included. No setter on purpose; tests use reflection (`ChapterPublicationTest::publishedAgo`). The migration backfilled existing published chapters to the migration time.
- Plain `new \DateTimeImmutable()` in the entity: `symfony/clock` is only transitive, and making it direct needs user approval.
- `isNew()` = published within `Chapter::NEW_FOR` (7 days). Home cards and chapter rows show a "New" badge; HTML stays edge-cached 5 min, which is fine for a day-scale flag.
- `ChapterRepository::findPublishedGroupedByWork()` replaced `findAllPublishedWithWork()`: one query, grouped in PHP, newest `updatedAt` (latest first publication) first. Used by home and sitemap (lastmod on every URL).
- Summary: reader header text, meta description and ComicIssue description (series); a oneshot keeps the work description.
- PHPStan remembers getter results across calls to setters on the same object (`assertNull`, then `setPublished`, then `assertNotNull` is flagged as impossible); use separate instances.
- Dev demo dates are spread (Neon Tide ch. 4 two days ago, Garden three days ago), and Neon Tide ch. 4 has a summary.

## Branding (2026-10-08, audit item 4, committed e4bf472)
- User chose admin-editable settings over env, and an optional per-work cover upload.
- `SiteSettings`: single row, id 1. Admin index creates it from `SITE_NAME`/`SITE_DESCRIPTION` on first visit, then redirects to edit (no new/delete/detail). `SiteSettingsProvider` caches a detached copy in cache.app; saving calls `invalidate()`. Twig global `site` (`SiteTwigGlobals`) replaced the env globals `site_name`/`site_description`. Tests run on the array cache, so settings cost one query there: the reader test counts 5.
- Gotcha: a `.css.twig` file autoescapes with the CSS strategy, so `#ff6a4d` came out as `\23 ff6a4d`, an ident rather than a colour, and broke the theme. `getAccent()` always returns valid hex (falls back to the default), and templates print it `|raw`.
- Uploads (logo, work cover) are transient entity properties with `Assert\Image`, processed after the flush by `CustomImages` (vips with the untrusted-loader block; temp files first, so a bad image changes nothing). Versioned URLs (`/media/site/logo-{v}.webp`, `/media/work/{id}/thumb-{v}.webp`, `cover-{v}.jpg`) are immutable. A work cover is public once any chapter of the work is published.
- An uploaded logo replaces both the mark and the name in the header (alt = name), and becomes the favicon and the EasyAdmin icon. Without a logo, `/favicon.svg` is a Twig route in the accent colour (static file deleted).
- `/about` shows bio and links, 404 when both are empty; `about` is a reserved slug. Links are "Label | https://..." lines, validated, so a `javascript:` URL never reaches an href.
- Dev DB: settings row with the default name and accent, demo bio and a Bluesky link; no logo, no work covers.

## Polish (2026-10-08, audit item 5, uncommitted)
- Reader: first page `<link rel=preload as=fetch crossorigin=anonymous>` (matches the controller's same-origin `fetch()`; verified one request, no console warning). Lighthouse prod-mode median LCP stayed 2.4 s (5 runs, was 2.3 s): `php -S` serves one request at a time, so the parallel fetch cannot show. Re-measure behind Nginx before judging; remove if it still shows nothing.
- Progress bar is CSS-only (`animation-timeline: scroll(root)` under `@supports`); browsers without scroll timelines (Firefox) show nothing. Arrow Left/Right go to the previous/next chapter (reader controller values; ignored with modifiers or in form fields); buttons carry `aria-keyshortcuts`.
- Logout: `enable_csrf: true`; EasyAdmin's menu link gets the token from `LogoutUrlGenerator`. A bare `/logout` now answers 403 (themed error page).
- Archive caps: 1000 images, 2 GB total uncompressed, checked from the central directory before any write. The page cap is tested; the 2 GB cap is not (building a 2 GB test archive is too slow).
- `fetchpriority="high"` on the first home cover and the work page cover.

## Full CSP (2026-10-08, uncommitted, same tree as polish)
- User chose hashes over nonces: nonces make every response unique, which breaks ETag/304 and would let Cloudflare serve one nonce to everyone. `SecurityHeaders` hashes each inline `<script>`/`<style>` of the final HTML (skips `src=` scripts and JSON/JSON-LD data blocks) into `script-src`/`style-src`; `style-src-attr 'unsafe-inline'` covers the page-size and EasyAdmin style attributes; `img-src 'self' data:`.
- Gotcha: browsers merge a 304's headers into the cached page, so a hash-less CSP on a 304 would break it. The listener runs at priority 16, before PublicPageCache (0) empties the body; the test asserts the 304 policy equals the 200's.
- `Content-Type` is not set yet during kernel.response (`prepare()` runs at send), so a missing type is treated as HTML; non-HTML bodies simply have no blocks.
- Verified in the browser: no violations on public pages, the reader (canvas, fetch), login, and every EasyAdmin page incl. dropdowns and the AJAX published switch. EasyAdmin has no inline scripts except its unused welcome page.
