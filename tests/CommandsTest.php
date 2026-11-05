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
use Crab\Runs\Commands\InstallRuns;
use Crab\Runs\RunsPlugin;
use PHPUnit\Framework\TestCase;

final class CommandsTest extends TestCase
{
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
