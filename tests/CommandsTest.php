<?php

declare(strict_types=1);

namespace Crab\Runs\Tests;

use Crab\Config\Config;
use Crab\Console\Console;
use Crab\Console\Output;
use Crab\Container\Container;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Migrator;
use Crab\Contracts\Database\Migrators;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Runs\Runs;
use Crab\Contracts\Runs\Status;
use Crab\Runs\Commands\InstallRuns;
use Crab\Runs\Commands\ListRuns;
use Crab\Runs\RunsPlugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidListOptions(): iterable
    {
        yield 'bare status' => [['--status'], '--status needs a value; use --status=failed'];
        yield 'separated status' => [['--status', 'failed'], '--status needs a value; use --status=failed'];
        yield 'bare limit' => [['--limit'], '--limit needs a value; use --limit=50'];
        yield 'separated limit' => [['--limit', '10'], '--limit needs a value; use --limit=50'];
        yield 'missing limit before invalid status' => [
            ['--status=unknown', '--limit'],
            '--limit needs a value; use --limit=50',
        ];
        yield 'unknown status' => [['--status=unknown'], 'use runs:list --status=failed'];
        foreach (['', '0', '-1', '1.5', '10runs', '100000', '999999999999999999999999'] as $value) {
            yield 'limit ' . $value => [['--limit=' . $value], 'use runs:list --limit=50'];
        }
    }

    #[DataProvider('invalidListOptions')]
    public function testListRefusesInvalidOptionsBeforeReadingRuns(array $arguments, string $message): void
    {
        $runs = $this->createMock(Runs::class);
        $runs->expects(self::never())->method('latest');

        [$status, $output] = $this->command(new ListRuns($runs), $arguments);

        self::assertSame(1, $status, $output);
        self::assertStringContainsString($message, $output);
    }

    public function testListPassesTheSelectedStatusAndLimit(): void
    {
        $runs = $this->createMock(Runs::class);
        $runs->expects(self::once())->method('latest')->with(Status::Waiting, 10)->willReturn([]);

        [$status, $output] = $this->command(new ListRuns($runs), ['--status=waiting', '--limit=10']);

        self::assertSame(0, $status, $output);
        self::assertSame("No run is waiting.\n", $output);
    }

    public function testInstallQuotesConfiguredTableNamesInBothDirections(): void
    {
        $tables = ["team's_runs", "team's_steps`\\archive"];
        $config = $this->config(['table' => $tables[0], 'steps' => $tables[1]]);
        $contents = '';
        $migrator = $this->createMock(Migrator::class);
        $migrator
            ->expects(self::once())
            ->method('install')
            ->willReturnCallback(static function (string $name, string $migration) use (&$contents): array {
                $contents = $migration;
                return ['migration.php', true];
            });
        $migrators = $this->createMock(Migrators::class);
        $migrators->expects(self::once())->method('migrator')->with(null)->willReturn($migrator);

        [$status, $output] = $this->command(new InstallRuns($migrators, $config));
        self::assertSame(0, $status, $output);
        $file = tempnam(sys_get_temp_dir(), 'crab-runs-migration-');
        try {
            file_put_contents($file, $contents);
            $migration = require $file;
        } finally {
            unlink($file);
        }
        self::assertInstanceOf(Migration::class, $migration);
        $created = [];
        $dropped = [];
        $db = $this->createMock(Connection::class);
        $db->method('driver')->willReturn('mysql');
        $db
            ->expects(self::once())
            ->method('execute')
            ->with("ALTER TABLE `team's_steps``\\archive` MODIFY `name` VARBINARY(191) NOT NULL")
            ->willReturn(0);
        $schema = $this->createMock(Schema::class);
        $schema->method('connection')->willReturn($db);
        $schema
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnCallback(static function (string $name) use (&$created): void {
                $created[] = $name;
            });
        $schema
            ->expects(self::exactly(2))
            ->method('drop')
            ->willReturnCallback(static function (string $name) use (&$dropped): void {
                $dropped[] = $name;
            });

        $migration->up($schema);
        $migration->down($schema);

        self::assertSame($tables, $created);
        self::assertSame(array_reverse($tables), $dropped);
    }

    public function testInstallChecksSettingsBeforeWritingAMigration(): void
    {
        $config = $this->config(['table' => '', 'steps' => [], 'database' => false]);
        $migrators = $this->createMock(Migrators::class);
        $migrators->expects(self::never())->method('migrator');

        [$status, $output] = $this->command(new InstallRuns($migrators, $config));

        self::assertSame(1, $status, $output);
        foreach (['runs.table', 'runs.steps', 'runs.database'] as $key) {
            self::assertStringContainsString($key, $output);
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function config(array $settings): Config
    {
        $config = new Config(['runs' => $settings]);
        $container = new Container();
        $container->instance(\Crab\Contracts\Config\Config::class, $config);
        new RunsPlugin()->register($container);
        return $config;
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string}
     */
    private function command(Command $command, array $arguments = []): array
    {
        $stream = fopen('php://memory', 'w+');
        try {
            $console = new Console();
            $console->add('runs:test', $command);
            $status = $console->run(['runs:test', ...$arguments], new Output($stream, $stream));
            rewind($stream);
            return [$status, stream_get_contents($stream)];
        } finally {
            fclose($stream);
        }
    }
}
