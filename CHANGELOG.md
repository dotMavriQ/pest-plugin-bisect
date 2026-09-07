# Changelog

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `pest --bisect=<seed>` reduces an order-dependent failure to the minimal ordered
  set of test files that reproduces it.
- `pest --bisect` searches random orders for a failure and then bisects it.
- `--bisect-attempts` bounds that search (default 25).
