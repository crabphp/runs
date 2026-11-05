<?php

declare(strict_types=1);

namespace Crab\Runs\Commands;

use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Runs\Runs;

final readonly class RecoverRuns implements Command
{
    public function __construct(
        private Runs $runs,
    ) {}

    public function description(): string
    {
        return <<<'TEXT'
            Give a job to each run whose own was lost
            A run pending or sleeping whose time came more than `runs.lease` seconds ago, untouched since, lost
            its job: its process died before pushing it, or the queue lost it. Recovery of a run left running
            waits out the longer of its lease and the queue's retry_after, plus another lease, after its
            lease ran out. A supervisor of the runs' queue may extend retry_after. That counts as a failed
            try. Schedule this every minute.
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        if ($input->arguments() !== []) {
            $output->error('runs:recover takes no arguments.');
            return 1;
        }
        $pushed = $this->runs->recover();
        $output->line($pushed === 0 ? 'Every run has its job.' : "{$pushed} runs were given a job.");
        return 0;
    }
}
