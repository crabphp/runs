# Changelog

Crab Runs follows [semantic versioning](https://semver.org). Before 1.0, a minor version may break what an application writes; the entry says what to change.

## Unreleased

- **Step names compare byte for byte on MySQL (migration required):** the default text collation could make `quote`, `Quote` and `quóte` share a kept value. New installations use `VARBINARY(191)` for `run_steps.name`. Existing MySQL tables need the migration in [Installation](README.md#installation); SQLite needs no change.
- **Generated migrations keep table names intact:** `runs:install` checks settings before writing and quotes configured table names as PHP literals.
- **A failed read ends its lease renewal:** loading a run's steps is inside its try, so a database error cannot leave a task renewing the run after its job has ended.
- **A signal cannot replace a running step's value:** a row kept while a step runs is checked for the same kind before its value is returned.
- **A cooperative queue timeout counts as a failed run try:** it follows `runs.tries` and `runs.backoff`. A worker cancellation still releases the run without consuming a try.
- **A run's result is checked inside its try:** a `handle()` return that cannot be kept as JSON now fails the try under `runs.tries` and `runs.backoff`, instead of leaving the run marked running for the queue to recover.
- **A transaction keeps the recovery delay:** `within()` preserves the longest queue supervisor's `retry_after` instead of returning to the default delay.
- **The runs contract covers maintenance and resumption (breaking for custom implementations):** `Contracts\Runs\Runs` declares the service's `latest()`, `recover()` and `resume()` methods. Implementations of the contract add all three; maintenance commands and resumption jobs now ask for the contract, and the concrete service name follows a replacement bound to it.
- **Value options require a value:** `runs:list` uses `Input::value()` to refuse a bare `--status` or `--limit`, with an example of the expected form.
- **Standalone settings are checked:** a `Runs` made directly refuses a backoff that is not a list of nonnegative whole seconds.
- **Framework 0.6 coverage:** CI checks 0.6.0, 0.6.1 and main on SQLite and MySQL. The README shows the full plugin setup, the queue migration and package configuration.

## 0.1.0 — 2026-11-05

- **Runs are a package of their own:** they were part of `crabphp/framework` up to 0.5.0 and are the same code, with the same classes, plugin, commands and settings. Require `crabphp/runs` beside the framework and nothing else changes. What came before is in [the framework's changelog](https://github.com/crabphp/framework/blob/main/CHANGELOG.md).
