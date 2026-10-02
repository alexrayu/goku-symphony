# phase-1-skeleton

## Status
Phase 1 built, uncommitted, awaiting user review at the checkpoint. Schema validate (--skip-sync), PHPStan level 8 and PHPUnit run clean inside the php container with `--no-deps`.

Open blocker: the MinIO image does not pull (Docker Hub `minio/minio` and `quay.io/minio/minio` both return unauthorized; postgres and redis pull fine). Options put to the user: build from source/pinned archive (recommended), swap to another S3-compatible container, or check daemon login/mirror. Awaiting choice. Until fixed, `make up/schema/stan/test` fail because every `run` starts the minio chain; use `docker compose --env-file versions.env run --rm --no-deps php ...`.

Next: user commits (4 suggested commits), then write root `CLAUDE.md` per the brief, then phase 2.

User-owned pieces left unwritten on purpose: `make check` target and the smoke test in `tests/`.

## Gotchas
- Composer's global GitHub token is stale; Flex recipe fetch 404s with it. Scaffold and require with a clean `COMPOSER_HOME`.
- `doctrine-bundle` pulls Symfony 8.1 components (clock, doctrine-bridge, options-resolver, stopwatch). Keep all `symfony/*` at 7.4; recheck the lock after any `composer require`.
- Do not set `config.platform.php` below the container's PHP patch version; packages needing `>=8.4.1` then fail to install.
- `versions.env` is passed with `--env-file`; Compose's default `.env` is Symfony's.
- Repo `.gitignore` is Symfony's; the original ignored `.claude`, which would exclude these committed memories.
- `tests/bootstrap.php` lost the recipe's `method_exists` guard because level 8 flags it.

## Decisions
- Dev PHP container runs `php -S` (no FPM/Nginx until phase 6).
- Added beyond the brief, user-approved: `league/flysystem-aws-s3-v3`, `symfony/test-pack`, `phpstan-symfony`, `phpstan-phpunit`.
- Work happens on `phase-1-skeleton` because memory hooks skip `master`.
