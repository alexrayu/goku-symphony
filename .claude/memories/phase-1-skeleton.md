# phase-1-skeleton

## Status
Phase 1, root CLAUDE.md and phase 2 deps are committed. Phase 2 built but uncommitted: entities, applied migration, Foundry factories, ChapterPagesTest, EasyAdmin dashboard + Work/Chapter CRUD. Schema validate, PHPStan level 8 and PHPUnit pass. `/admin` is unauthenticated until phase 3.

Storage decision: local Flysystem adapter on a protected dir (`STORAGE_PATH`), no S3/MinIO. Originals never web-reachable; derivatives public via a controller; `X-Accel-Redirect` in phase 6.

`ChapterRepository::findPublishedWithPages(Work)` written (fetch join, 1 query; ORM applies mapping OrderBy to the join) with test.

Phase 3 (security) built, uncommitted: form_login + CSRF, logout, login_throttling (5 attempts), `/admin` requires ROLE_USER, login template, `app:user:create` command (non-empty password only, no length rule by user's choice). PHPStan level 8 passes; `debug:router` shows login/logout; command rejects a bad email. PHPUnit not rerun, browser flow not yet checked by the user (php container was down; `make up` first). Next: user reviews phase 3 checkpoint, then phase 4.

User-owned pieces left unwritten on purpose: `make check` target, the smoke test in `tests/`, the page position-gap helper test, phase 3 login functional test (anon /admin redirect, good login, bad login; UserFactory may need a hashed-password default).

## Gotchas
- Composer's global GitHub token is stale; Flex recipe fetch 404s with it. Scaffold and require with a clean `COMPOSER_HOME`.
- `doctrine-bundle` pulls Symfony 8.1 components (clock, doctrine-bridge, options-resolver, stopwatch). Keep all `symfony/*` at 7.4; recheck the lock after any `composer require`.
- Do not set `config.platform.php` below the container's PHP patch version; packages needing `>=8.4.1` then fail to install.
- `versions.env` is passed with `--env-file`; Compose's default `.env` is Symfony's.
- Repo `.gitignore` is Symfony's; the original ignored `.claude`, which would exclude these committed memories.
- `tests/bootstrap.php` lost the recipe's `method_exists` guard because level 8 flags it.

## Decisions
- Dev PHP container runs `php -S` (no FPM/Nginx until phase 6).
- Added beyond the brief, user-approved: `symfony/test-pack`, `phpstan-symfony`, `phpstan-phpunit`.
- Work happens on `phase-1-skeleton` because memory hooks skip `master`.
- Phase 2 choices: `Chapter.number` is NUMERIC(6,1) (extras like 12.5); `Work.slug` unique; no cascade on `Work.chapters` (delete fails on FK on purpose); no roles column on `User`, `getRoles()` is constant; oneshot "exactly one Chapter" is enforced in a service later, not in the DB.
- EasyAdmin's recipe pulled in security-bundle config; phase 3 rewrote it.
- `config/packages/csrf.yaml` (EasyAdmin recipe) listed `authenticate`/`logout` as stateless token ids; stateless tokens need the Stimulus csrf controller (no `assets/` yet), so login always failed "Invalid CSRF token". Now only `submit` is stateless. Re-add them only together with the UX Stimulus bridge.
- Phase 3 login flow verified by curl (anon redirect, bad csrf, bad password, login, logout, throttle). After login the redirect goes to `/`, which has no route yet.
- Installer (user-approved override of "installer out of scope" and "users by console only"): empty `app_user` redirects every path to `/install`; open form (email + password + confirm) creates only the first user, logs in, redirects to `/admin`. Install token was built then removed at user's request: the installer is claimable by whoever reaches it first, so install before a public droplet is reachable. Scope is first user only, no migrations/env writing (Docker/Ansible own those). `UserProvisioner` is the shared creation path (command + installer).
- Installer subscriber runs at priority 64 on purpose: the router (32) throws 404 for `/` before a later listener could redirect, and the firewall (8) would otherwise send `/admin` to `/login` first.
- Gotcha: any future functional test of public pages needs a user in the DB, or it gets redirected to `/install`. `InstallState` caches only "installed = true" (array cache in test env); after deleting all users in dev, run `bin/console cache:pool:clear cache.app` (not `cache:clear`) to see the installer again.
- Dev user ids are not 1 after earlier test users (sequence keeps counting); only a fresh DB gives id 1.
- Browser testing: no Chrome MCP here. Headless Chromium via Playwright in a Python venv in the scratchpad (system Node 18 is too old for current npm playwright; do not upgrade system Node). Cached browser at `~/.cache/ms-playwright/chromium-1208`.
- Added `symfony/rate-limiter` (required by `login_throttling`); lock verified free of symfony/* 8.x afterwards.
