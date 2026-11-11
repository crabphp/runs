# Contributing

Setup, tests, formatting, linting and conventions are the same as the framework's: see [crabphp/framework's CONTRIBUTING.md](https://github.com/crabphp/framework/blob/main/CONTRIBUTING.md). Before a pull request, run here:

```sh
composer format:check
composer lint
composer test
```

Composer reads the framework from `../framework`; keep it on a version satisfying `^0.7` and run `composer install` here.

The suite uses SQLite unless `CRAB_TEST_MYSQL_DSN` is set. To exercise MySQL too, use a dedicated database: the tests drop `runs` and `run_steps`.

```sh
CRAB_TEST_MYSQL_DSN='mysql:host=127.0.0.1;dbname=crab_test;charset=utf8mb4' composer test
```

Set `CRAB_TEST_MYSQL_USER` and `CRAB_TEST_MYSQL_PASSWORD` if the test server does not accept `root` with an empty password. CI runs both database suites against framework 0.6.0, 0.6.1 and main.
