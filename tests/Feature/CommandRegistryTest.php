<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\CommandNotFoundException;
use Bityukov\CommandCenter\Sources\CommandSource;

function fakeSource(CommandDefinition ...$definitions): CommandSource
{
    return new class(...$definitions) implements CommandSource
    {
        /** @var array<int, CommandDefinition> */
        private array $definitions;

        public function __construct(CommandDefinition ...$definitions)
        {
            $this->definitions = $definitions;
        }

        public function definitions(): array
        {
            $keyed = [];

            foreach ($this->definitions as $definition) {
                $keyed[$definition->key] = $definition;
            }

            return $keyed;
        }
    };
}

it('exposes definitions from the config source', function (): void {
    config()->set('command-center.commands', ['clear-cache' => ['run' => 'cache:clear']]);

    $registry = app(CommandRegistry::class);

    expect(array_keys($registry->all()))->toBe(['clear-cache']);
});

it('lets a later source override an earlier one', function (): void {
    config()->set('command-center.commands', ['x' => ['run' => 'first']]);

    $registry = app(CommandRegistry::class);
    $registry->addSource(fakeSource(
        Command::make('x')->run('second')->toDefinition(defaultTimeout: 60),
    ));

    expect($registry->all()['x']->run)->toBe('second');
});

it('finds a definition by key', function (): void {
    config()->set('command-center.commands', ['clear-cache' => ['run' => 'cache:clear']]);

    $registry = app(CommandRegistry::class);

    expect($registry->find('clear-cache'))->not->toBeNull()
        ->and($registry->find('nope'))->toBeNull();
});

it('throws when a required definition is missing', function (): void {
    config()->set('command-center.commands', []);

    app(CommandRegistry::class)->findOrFail('nope');
})->throws(CommandNotFoundException::class, 'nope');

it('groups definitions, defaulting ungrouped ones', function (): void {
    config()->set('command-center.commands', [
        'a' => ['run' => 'a', 'group' => 'Database'],
        'b' => ['run' => 'b'],
        'c' => ['run' => 'c', 'group' => 'Database'],
    ]);

    $grouped = app(CommandRegistry::class)->grouped();

    expect(array_keys($grouped))->toBe(['Database', 'Commands'])
        ->and(array_keys($grouped['Database']))->toBe(['a', 'c'])
        ->and(array_keys($grouped['Commands']))->toBe(['b']);
});

it('queries each source only once until flushed', function (): void {
    config()->set('command-center.commands', []);

    $source = new class implements CommandSource
    {
        public int $calls = 0;

        public function definitions(): array
        {
            $this->calls++;

            return [];
        }
    };

    $registry = app(CommandRegistry::class);
    $registry->addSource($source);

    $registry->all();
    $registry->all();
    $registry->all();

    expect($source->calls)->toBe(1);

    $registry->flush();
    $registry->all();

    expect($source->calls)->toBe(2);
});

it('reflects new definitions from a source after flushing', function (): void {
    config()->set('command-center.commands', []);

    $source = new class implements CommandSource
    {
        /** @var array<string, CommandDefinition> */
        public array $definitions = [];

        public function definitions(): array
        {
            return $this->definitions;
        }
    };

    $registry = app(CommandRegistry::class);
    $registry->addSource($source);

    expect($registry->all())->toBe([]);

    $source->definitions = [
        'later' => Command::make('later')->run('cache:clear')->toDefinition(defaultTimeout: 60),
    ];

    expect(array_keys($registry->all()))->toBe([]);

    $registry->flush();

    expect(array_keys($registry->all()))->toBe(['later']);
});

it('resolves one shared instance per container scope', function (): void {
    expect(app(CommandRegistry::class))->toBe(app(CommandRegistry::class));
});

it('is rebuilt when the container scope is reset', function (): void {
    // Scoped rather than singleton: a queue worker or Octane process resets
    // scoped instances between jobs and requests, so a registry that memoized
    // definitions from a mutable source cannot keep serving revoked ones.
    $first = app(CommandRegistry::class);

    app()->forgetScopedInstances();

    expect(app(CommandRegistry::class))->not->toBe($first);
});
