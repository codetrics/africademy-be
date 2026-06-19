---
name: new-command
description: Scaffold a Symfony console command for this project. Use when the user asks to create a command, CLI task, or scheduled job. All commands extend AbstractCommand (not Command directly) which provides file-based locking and per-command logging.
disable-model-invocation: false
argument-hint: [CommandClassName]
allowed-tools: Read Write Glob Grep Bash(ls *)
---

Create a new console command named `$ARGUMENTS`.

## Live context

Existing commands (check for duplicates and follow naming patterns):
```!
ls src/Command/
```

AbstractCommand base class (read before scaffolding to understand what's inherited):
```!
cat src/Command/AbstractCommand.php
```

## Instructions

1. **Check for duplicates** — if `src/Command/$ARGUMENTS.php` already exists, stop.
2. **Read `AbstractCommand.php`** (shown above) before writing — understand what `getLogger()`, `finalize()`, and `getLockName()` provide.
3. **Class name convention:** verb-noun style, e.g. `ProcessQueueCommand`, `SendDigestCommand`, `PruneTokensCommand`.
4. **File location:** `src/Command/$ARGUMENTS.php`

## Checklist

- [ ] `declare(strict_types=1)` at the top
- [ ] Extend `AbstractCommand` — **never** `Command` directly
- [ ] `#[AsCommand(name: 'app:noun:verb', description: '...')]` — command name uses `app:` prefix and kebab-case
- [ ] Constructor: property-promoted `private readonly` dependencies; `parent::__construct()` as the **last** statement
- [ ] `execute()` returns `Command::SUCCESS` or `Command::FAILURE` — never an arbitrary int
- [ ] Core logic wrapped in try/catch; log exception and return `FAILURE` on error
- [ ] Use `$this->getLogger()->info/error/warning(...)` — do not use `$output->writeln()` for structured logging
- [ ] Do NOT call `$this->finalize()` manually — `AbstractCommand::run()` calls it in a `finally` block

## Template

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExampleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:{resource}:{verb}', description: 'Describe what this command does')]
class $ARGUMENTSCommand extends AbstractCommand
{
    public function __construct(
        private readonly ExampleService $exampleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->getLogger()->info('Starting $ARGUMENTS');

            // business logic here

            $this->getLogger()->info('$ARGUMENTS completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
            return Command::FAILURE;
        }
    }
}
```

Replace `{resource}`, `{verb}`, and the service dependency with the actual command's purpose.
If `$ARGUMENTS` already ends in `Command`, use it as-is; otherwise append `Command`.
