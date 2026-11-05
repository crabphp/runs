# Changelog

Crab Runs follows [semantic versioning](https://semver.org). Before 1.0, a minor version may break what an application writes; the entry says what to change.

## Unreleased

- **A failed read ends its lease renewal:** loading a run's steps is inside its try, so a database error cannot leave a task renewing the run after its job has ended.
- **A signal cannot replace a running step's value:** a row kept while a step runs is checked for the same kind before its value is returned.
- **A cooperative queue timeout counts as a failed run try:** it follows `runs.tries` and `runs.backoff`. A worker cancellation still releases the run without consuming a try.
- **A run's result is checked inside its try:** a `handle()` return that cannot be kept as JSON now fails the try under `runs.tries` and `runs.backoff`, instead of leaving the run marked running for the queue to recover.
- **A transaction keeps the recovery delay:** `within()` preserves the longest queue supervisor's `retry_after` instead of returning to the default delay.
- **Standalone settings are checked:** a `Runs` made directly refuses a backoff that is not a list of nonnegative whole seconds.

## 0.1.0 — 2026-11-05

- **Runs are a package of their own:** they were part of `crabphp/framework` up to 0.5.0 and are the same code, with the same classes, plugin, commands and settings. Require `crabphp/runs` beside the framework and nothing else changes. What came before is in [the framework's changelog](https://github.com/crabphp/framework/blob/main/CHANGELOG.md).
