# phase-1-skeleton

## Status
Phase 1, root CLAUDE.md and phase 2 deps are committed. Phase 2 built but uncommitted: entities, applied migration, Foundry factories, ChapterPagesTest, EasyAdmin dashboard + Work/Chapter CRUD. Schema validate, PHPStan level 8 and PHPUnit pass. `/admin` is unauthenticated until phase 3.

Storage decision: local Flysystem adapter on a protected dir (`STORAGE_PATH`), no S3/MinIO. Originals never web-reachable; derivatives public via a controller; `X-Accel-Redirect` in phase 6.

`ChapterRepository::findPublishedWithPages(Work)` written (fetch join, 1 query; ORM applies mapping OrderBy to the join) with test, uncommitted. Next: phase 2 checkpoint, then phase 3 (security).

User-owned pieces left unwritten on purpose: `make check` target, the smoke test in `tests/`, the page position-gap helper test.

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
- EasyAdmin's recipe pulled in security-bundle config; phase 3 rewrites it.
