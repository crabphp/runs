# Agents

The framework's [AGENTS.md](https://github.com/crabphp/framework/blob/main/AGENTS.md) holds for this package: its rules for code, comments, tests and documentation all apply. What follows is particular to runs.

## Runs

- A run is a class like a job whose `handle()` also takes `Contracts\Runs\Run`. It is resumed by running `handle()` from the top: a step, sleep or signal already kept returns what was kept. There is no event history, no replay log and no serialized fiber: a run's state is its rows in two tables.
- What a run keeps is JSON, and a step's value is handed back as JSON reads it the first time too, so that a fresh try and a resumed one see the same value.
- A step is kept with `insertOrIgnore()` on a unique run and name, and read back: should two tries race, both go on with the first one's value. A step runs at least once, as a job does, and the guide says so.
- One worker runs a run at a time, holding a lease it renews every third of `runs.lease`; a resumption that finds it held does nothing but look again when the lease would run out. Sleep and await end a try by throwing `Runs\Suspended` and push the resumption through the queue; neither holds a worker.
- Runs count and space their own tries (`runs.tries`, `runs.backoff`); the `ResumeRun` job's own tries are only for its worker dying or being stopped.
