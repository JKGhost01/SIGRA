# Contributing to SIGRA

Thanks for helping improve an open seismic-monitoring project. This guide keeps
contributions consistent and reviewable.

## Before you start

- Search existing issues and pull requests.
- For a large feature, open an issue first so we can agree on the approach.
- Do not publish credentials, private telemetry or personal user data in public issues.

## Development setup

Follow the single [installation procedure](docs/INSTALLATION.md):

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
# Create an empty database and configure .env.
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

The repository includes CI4 under `system/`, not `vendor/`. Composer installs the
locked runtime libraries and PHPStan without running framework-development scripts.
Automated checks additionally require Node 22+, Python 3 and PHP-CGI. Run
`composer test` and `composer analyse`; database integration is opt-in and isolated.

## Code style

- PHP 8.2+, `declare(strict_types=1);` in new classes.
- Follow PSR-12 and the [modular architecture](docs/architecture/README.md):
  - controllers parse HTTP input, call use cases and produce responses;
  - application handlers coordinate operations through injected contracts;
  - domain rules stay independent of CI4;
  - infrastructure owns persistence, providers and external delivery;
  - views only render prepared data; they do not validate permissions or execute writes.
- Existing `app/Libraries` and `app/Models` compatibility names delegate to modules;
  new business behavior belongs to its capability module.
- Escape all external data in HTML (`esc()` in PHP, `escaparHtml()` in JS).
- Do not hardcode credentials, API keys, phone numbers or production domains.
- Keep new JavaScript framework-free and compatible with current evergreen
  browsers.

## Tests and checks

```bash
# Check ignored runtime artifacts and source visibility
python3 tools/check-git.py

# Architecture, domain, HTTP and presentation regression checks
composer test

# Syntax check every PHP file you touched
php -l app/Path/To/File.php

# JavaScript syntax
node --check public/assets/js/mapa-sismos.js

# Manual smoke test
php spark serve
# open /sismos, /sismos/historico, /sismos/detalle/{id}, /api/health
```

When adding a source normalizer, include fixtures and a unit test covering:

- a valid event,
- a missing magnitude/coordinate,
- a timezone conversion,
- an ID longer than 24 characters,
- a duplicate against another source.

## Commits and pull requests

- Use clear, imperative commit messages: `Add EMSC normalizer`, `Fix radius API`.
- One logical change per pull request.
- Describe **what** changed, **why**, and how it was tested.
- Update documentation and `docs/VERSIONES.md` when the public behaviour changes.
- Do not bump the version in a feature PR unless a maintainer asks you to.
- For the modular migration, include new modules and retired paths in the same
  commit with `git add -A`; see the [Git publication guide](docs/architecture/git-publication.md).

## Reporting bugs

Include:

- SIGRA version (`/api/health`),
- PHP and database versions,
- browser/OS or Android model,
- steps to reproduce,
- expected vs. actual behavior,
- relevant lines from `writable/logs/` with secrets removed.
