# Working on this

## Setup

```bash
composer install
composer fixture:install
```

`fixture:install` installs a small Pest project under `fixtures/flaky` with a
planted order dependency. The feature tests run the plugin against it.

If you do not have PHP locally, build the container image and run commands
through `dev.sh`:

```bash
podman build -t pest-dev .
./dev.sh composer install
./dev.sh composer fixture:install
./dev.sh composer test
```

## Checks

```bash
composer test          # pint, phpstan, pest
composer test:coverage # pest with a 95% line coverage floor
```

`.github/workflows/tests.yml` runs the same checks on PHP 8.4 and 8.5, against the
lowest and the latest allowed dependency versions.

## Conventions

Style is Pint's Laravel preset. Static analysis is PHPStan at max level with no
baseline. Keep both clean. Follow SemVer for anything user facing.

## Releasing

First time only:

1. The repository must be public for Packagist to index it.
2. Submit the repository URL at https://packagist.org/packages/submit.
3. Install the Packagist GitHub app (https://github.com/apps/packagist) on the
   repository so new tags sync automatically.
4. Once the package is on Packagist and CI has run on the default branch, add the
   badges to the top of `README.md`:

   ```markdown
   [![Latest version](https://img.shields.io/packagist/v/dotmavriq/pest-plugin-bisect.svg)](https://packagist.org/packages/dotmavriq/pest-plugin-bisect)
   [![Tests](https://github.com/dotmavriq/pest-plugin-bisect/actions/workflows/tests.yml/badge.svg)](https://github.com/dotmavriq/pest-plugin-bisect/actions/workflows/tests.yml)
   [![License](https://img.shields.io/packagist/l/dotmavriq/pest-plugin-bisect.svg)](LICENSE.md)
   ```

Each release:

1. Move the `[Unreleased]` block in `CHANGELOG.md` under a new version heading with
   today's date. Commit it.
2. Tag and push:

   ```bash
   git tag v1.0.0
   git push origin main --tags
   ```

   Composer reads versions from tags. Without a tag the package only resolves as
   `dev-main`.
3. Packagist picks the tag up within a minute. Confirm the new version shows at
   https://packagist.org/packages/dotmavriq/pest-plugin-bisect.
4. Optionally cut a GitHub release from the tag with the changelog entry as the
   notes.
