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

The package is on Packagist and updates from GitHub tags automatically.

For each release:

1. Move the `[Unreleased]` block in `CHANGELOG.md` under a new version heading with
   today's date, and add the compare link at the bottom. Commit it.
2. Tag and push:

   ```bash
   git tag -a v1.1.0 -m v1.1.0
   git push origin main --tags
   ```

   Composer reads versions from tags. Without a tag a change only resolves as
   `dev-main`.
3. Cut a GitHub release from the tag with the changelog entry as the notes.
3. Packagist picks the tag up within a minute. Confirm the new version shows at
   https://packagist.org/packages/dotmavriq/pest-plugin-bisect.
4. Optionally cut a GitHub release from the tag with the changelog entry as the
   notes.
