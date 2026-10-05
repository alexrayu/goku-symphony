# phase-1-skeleton

## Status
Phases 1-3 committed (skeleton, domain + EasyAdmin, auth + first-run installer).

Phase 4 (ingestion) built, uncommitted. PHPStan level 8 clean. Verified end to end in headless Chromium with a live worker: upload a ZIP on a Chapter, worker extracts, natural order, WebP derivatives, all pages `ready`. 10 of 11 tests pass; the pipeline test fails until the user writes `ArchivePageOrder::sort()` (stub throws LogicException on purpose). Real uploads also fail in the worker until then. Next: user writes sort + its unit test, phase 4 checkpoint, then phase 5 (reader).

Dev DB has a demo "E2E Test Work" with chapter 1 and 4 processed pages (files in `var/storage`), left for the user to look at.

User-owned pieces left unwritten on purpose: `make check` target, smoke test in `tests/`, page position-gap helper test, phase 3 login functional test (UserFactory password is "!", so use `loginUser()` or add a hashed default), phase 4 `ArchivePageOrder::sort()` + `tests/Ingest/ArchivePageOrderTest.php`.

Open question to the user: trim the `LATER.md` web-installer line to what is left (requirements check, DB credentials, `.env.local`, migrations).

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

## Decisions
- Dev PHP container runs `php -S` (no FPM/Nginx until phase 6). Uploads capped at 200M via `conf.d/uploads.ini` in the Dockerfile; prod Nginx needs matching `client_max_body_size`.
- Added beyond the brief, user-approved: `symfony/test-pack`, `phpstan-symfony`, `phpstan-phpunit`, `symfony/rate-limiter` (login throttling), `symfony/process` (vips CLI).
- Work happens on `phase-1-skeleton` because memory hooks skip `master`.
- Phase 2: `Chapter.number` NUMERIC(6,1); `Work.slug` unique; no cascade on `Work.chapters`; no roles column, `getRoles()` constant; oneshot rule enforced in a service later.
- Installer (user-approved override of "installer out of scope" / "users by console only"): open form, first user only, no token (user removed it; install before a droplet is public). `UserProvisioner` is the one user-creation path.
- Phase 4: libvips via CLI + `symfony/process`, not FFI. One WebP, max 1200 wide, Q80, never upscaled, height capped at 16383 (WebP limit) so very tall strips get narrower (a 900x30000 strip becomes 491x16383); slicing strips is a later candidate. Stored dimensions are the derivative's. Re-upload to a chapter with pages is rejected. Originals and derivatives use generated keys (no entry names: no traversal); 64 MB uncompressed cap per entry. Final derivative failure sets page `failed` via a WorkerMessageFailedEvent listener. Derivative key is derived (`derivatives/{chapter}/{page}.webp`), no column.
- Deleting a chapter removes page rows (cascade) but not storage files; not handled yet.
- Browser testing: no Chrome MCP. Headless Chromium via Playwright in a Python venv in the scratchpad (system Node 18 too old for npm playwright; do not upgrade system Node). Cached browser at `~/.cache/ms-playwright/chromium-1208`. Run a worker with `messenger:consume async --time-limit=300` in the background.
