<?php

declare(strict_types=1);

namespace Crab\Runs\Commands;

use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Runs\Runs;
use Crab\Contracts\Runs\Status;

final readonly class ListRuns implements Command
{
    public function __construct(
        private Runs $runs,
    ) {}

    public function description(): string
    {
        return <<<'TEXT'
            List the latest runs and where each is
            Options:
              --status=failed   Only those in one state: pending, running, sleeping, waiting, completed or failed
              --limit=50        How many, from 1 to 99999, the newest first

            A failed run is tried again with `runs:retry`.
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        $given = $input->value('status', 'failed');
        $limit = $input->value('limit', '50') ?? '50';
        $status = $given === null ? null : Status::tryFrom($given);
        if ($given !== null && $status === null) {
            $output->error(
                '--status takes '
                . implode(', ', array_map(static fn(Status $status): string => $status->value, Status::cases()))
                . '; use runs:list --status=failed.',
            );
            return 1;
        }
        if (preg_match('/^[1-9][0-9]{0,4}$/D', $limit) !== 1) {
            $output->error('--limit takes a whole number from 1 to 99999; use runs:list --limit=50.');
            return 1;
        }
        $runs = $this->runs->latest($status, (int) $limit);
        if ($runs === []) {
            $output->line($status === null ? 'No run has been started.' : "No run is {$status->value}.");
            return 0;
        }
        $rows = [['Id', 'Run', 'Status', 'Steps', 'Tries', 'Updated', 'Why']];
        foreach ($runs as $run) {
            $why = (string) strtok((string) $run->error, "\n");
            $rows[] = [
                (string) $run->id,
                $run->run,
                $run->status->value,
                (string) count($run->steps),
                (string) $run->attempts,
                $run->updatedAt->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
                strlen($why) > 80 ? substr($why, 0, 80) . '...' : $why,
            ];
        }
        $output->table($rows);
        return 0;
    }
}
