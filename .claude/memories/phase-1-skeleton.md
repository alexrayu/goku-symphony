# phase-1-skeleton

## Status
Phases 1-3 committed (skeleton, domain + EasyAdmin, auth + first-run installer).

Phase 4 (ingestion) committed (a033e81). PHPStan level 8 clean. Verified end to end in headless Chromium with a live worker: upload a ZIP on a Chapter, worker extracts, natural order, WebP derivatives, all pages `ready`. All tests pass.

Phase 5 (reader) built, uncommitted, verified in headless Chromium (anonymous): `/` lists works with published chapters, `/w/{slug}` lists chapters (oneshot: reader in place), `/w/{slug}/{number}` reader, `/media/page/{id}.webp` streams derivatives. 21 tests pass, PHPStan clean. Lazy loading is native `loading="lazy"` (user said continue without picking; recommended option), no Stimulus/AssetMapper yet. Phase 5 checkpoint closed 2026-10-06: oneshot extra query left as is (recommended option). User commits phase 5, then phase 6 (Ansible/prod).

Dev DB has a demo oneshot "E2E Test Work", chapter 1 published, 4 labelled pages ("Page 1", "Page 2", "Page 10", tall strip) at `/w/e2e-test-work`.

User-owned pieces left unwritten on purpose: `make check` target, smoke test in `tests/`, page position-gap helper test, phase 3 login functional test (UserFactory password is "!", so use `loginUser()` or add a hashed default). `ArchivePageOrder::sort()` + its test were meant for the user, but they asked Claude to write them and will study them later.

Open question to the user: trim the `LATER.md` web-installer line to what is left (requirements check, DB credentials, `.env.local`, migrations).

## Working mode
- 2026-10-06: user switched to build mode (see CLAUDE.md). Build phase 6 without teaching stops; the user studies the code and asks for teaching later.

## Phase 6 decisions (2026-10-06)
- Droplet Ubuntu 24.04; PHP 8.4 from ondrej PPA (8.3 rejected: 26 locked packages need 8.4). PG 16, Redis 7 from distro.
- Deploy = git clone of public GitHub repo (`alexrayu/goku-symphony`) into releases/, symlink switch.
- Secrets in untracked `ansible/secrets.yml` + committed example; no vault.
- No droplet yet: verify against a local Ubuntu 24.04 systemd container. Harness (Dockerfile, inventory, key, self-signed cert, Playwright venv, e2e.py) in `~/Documents/tickets/goku-symfony/phase-6/`; container `goku-prodtest`, ports 2222/8443, host `goku.test`. Deploy test: `docker cp` a bare clone to `/opt/goku.git` (chown goku), `-e app_repo=file:///opt/goku.git -e app_ref=phase-1-skeleton`. Test secrets go in gitignored `ansible/secrets.yml`; delete after.
- Status 2026-10-06: provision idempotent, deploy + full slice E2E pass in the container (install, upload, worker, derivatives, reader, X-Accel media, draft 404). Pending: user commits the Flysystem permissions fix, then a second deploy is tested.
- MediaController uses BinaryFileResponse on `STORAGE_PATH` (not Flysystem) so `SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER=1` + Nginx `X-Accel-Mapping` hand the body to Nginx.
- Cloudflare Free caps request bodies at 100 MB; upload limit stays 200M (origin only). Told user.

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
- Work happens on `phase-1-skeleton` because memory hooks skip `master`.
- Phase 2: `Chapter.number` NUMERIC(6,1); `Work.slug` unique; no cascade on `Work.chapters`; no roles column, `getRoles()` constant; oneshot rule enforced in a service later.
- Installer (user-approved override of "installer out of scope" / "users by console only"): open form, first user only, no token (user removed it; install before a droplet is public). `UserProvisioner` is the one user-creation path.
- Phase 4: libvips via CLI + `symfony/process`, not FFI. One WebP, max 1200 wide, Q80, never upscaled, height capped at 16383 (WebP limit) so very tall strips get narrower (a 900x30000 strip becomes 491x16383); slicing strips is a later candidate. Stored dimensions are the derivative's. Re-upload to a chapter with pages is rejected. Originals and derivatives use generated keys (no entry names: no traversal); 64 MB uncompressed cap per entry. Final derivative failure sets page `failed` via a WorkerMessageFailedEvent listener. Derivative key is derived (`derivatives/{chapter}/{page}.webp`), no column.
- Deleting a chapter removes page rows (cascade) but not storage files; not handled yet.
- Phase 5: media controller checks published (or logged in) per image request: one PK query, so drafts are not guessable by id. Published images get `public, max-age=1y, immutable` (new upload = new page ids); drafts `private, no-store`. Phase 6 keeps the PHP check and swaps the body for `X-Accel-Redirect`. All image URLs come from `PageImageUrlGenerator` (Twig `page_image_url()`). Reader filters non-ready pages in PHP, not in the fetch-join WHERE (partial collection trap). `Chapter::getNumberLabel()` drops ".0" for URLs/headings.
- Browser testing: no Chrome MCP. Headless Chromium via Playwright in a Python venv in the scratchpad (system Node 18 too old for npm playwright; do not upgrade system Node). Cached browser at `~/.cache/ms-playwright/chromium-1208`. Run a worker with `messenger:consume async --time-limit=300` in the background.
