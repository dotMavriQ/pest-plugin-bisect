# pest-plugin-bisect

[![Tests](https://github.com/dotMavriQ/pest-plugin-bisect/actions/workflows/tests.yml/badge.svg)](https://github.com/dotMavriQ/pest-plugin-bisect/actions/workflows/tests.yml)

A Pest plugin that isolates order-dependent test failures.

When a suite passes in its usual order but fails under `--order-by=random`, the
failing test is rarely the cause. Something that ran earlier left state behind:
a static property, a mutated singleton, a frozen clock, an environment variable,
a record in an in-memory database. Everything runs in one PHP process, so that
residue is visible to every test that follows. Randomising the order just changes
which test trips over it.

PHPUnit reproduces the failure if you feed it the seed, but it will not tell you
which earlier test is responsible. Finding that by hand means commenting out
halves of the suite and re-running until the failure moves. This plugin does that
search for you.

```
$ pest --bisect=1

  ...

  Seed       1
  Failing    it requires a clean global state (tests/AaaVictimTest.php)
  Before it  5 files


   BISECT  Narrowed 6 files down to 2.

  This order reproduces the failure:

   1  tests/ZzzCulpritTest.php
   2  tests/AaaVictimTest.php  ← it requires a clean global state

  Replay it:

  pest --bisect=1
```

`tests/ZzzCulpritTest.php` is the one leaving state behind. That run is the
`fixtures/flaky` suite in this repository; clone it and try it. On a real suite
the list before the failure is hundreds of files rather than five, which is the
case this is for.

## Requirements

PHP 8.4, Pest 5.1 or newer.

## Install

```
composer require --dev dotmavriq/pest-plugin-bisect
```

The plugin registers itself through Pest's plugin system. `pest --bisect` is
available straight away, no configuration.

## Usage

A CI run failed and printed a seed:

```
Random Order Seed: 48291
```

Pass it to `--bisect`:

```
pest --bisect=48291
```

If you do not have a seed, `pest --bisect` runs the suite in random orders until
one fails and then bisects that order. `--bisect-attempts` sets how many orders it
tries before giving up (default 25):

```
pest --bisect --bisect-attempts=100
```

The bisect runs sequentially. `--bisect` and `--parallel` cannot be combined.

## How it works

1. Run the suite for the failing seed. Take the execution order and the name of
   the failing test from the JUnit report.
2. Treat every test file that ran before the failing one as a suspect.
3. Shrink the suspect list with delta debugging: run ordered subsets of it,
   followed by the failing test, and keep only the files needed for the failure
   to still occur.
4. Print what is left.

Step 3 needs PHPUnit to run a given set of files in a given order. There is no
flag for that, so the plugin writes a temporary configuration with an explicit
ordered `<testsuite>` and runs it with `--order-by=default`. The `bootstrap`
attribute and the `<php>` block from the project configuration are copied across
so each subset runs in the same environment as the full suite.

The shrink step is ddmin, from Zeller and Hildebrandt, *Simplifying and Isolating
Failure-Inducing Input* (IEEE TSE, 2002). When one earlier test is at fault it
settles in a number of runs roughly logarithmic in the count of suspects. Several
tests interacting is the quadratic worst case.

## Scope

Bisection is at file granularity. If the dependency is between two tests in the
same file, the result names that file on both sides, and splitting the file
isolates it further. File granularity covers the common case, where the leak
crosses a class boundary, and it keeps each step to one process per file instead
of one per test.

The plugin assumes the failure is deterministic for a given order. A test that
fails at random regardless of order is a separate problem and this will not find
it.

## Prior art

This is a port of an idea that already exists elsewhere.

- `git bisect` applies the same binary search to commit history.
- RSpec's `--bisect` and the `minitest-bisect` gem do this for Ruby test suites.
  This plugin is the Pest equivalent.
- Both, and this, are delta debugging (Zeller, Hildebrandt, 2002).

## License

MIT. See [LICENSE.md](LICENSE.md).
