# Command sources

A source answers one question: which commands exist. Every source produces
`CommandDefinition` objects, so by the time anything executes a command, where
it came from no longer matters — the same guards apply either way.

## Config source

The default. Commands live in `config/command-center.php`, are version
controlled, and survive `config:cache`.

```php
'commands' => [
    'backup-db' => [
        'run' => 'backup:run {database}',
        'label' => 'Backup database',
        'group' => 'Maintenance',
        'timeout' => 600,
        'queue' => 'long-running',
        'ability' => 'run-backups',
        'confirm' => 'This locks the database briefly. Continue?',
        'variables' => [
            'database' => ['type' => 'select', 'options' => ['main' => 'Main'], 'required' => true],
        ],
        'flags' => ['--force' => ['label' => 'Force']],
    ],
],
```

Closures are not available here — `visible`, dynamic options and
`modifyQueryUsing` need the fluent API. That is the price of `config:cache`
compatibility, and it is deliberate.

## Database source

Opt in by adding `DatabaseSource::class` to `sources`, publishing the migration
and running it. Commands are then editable in the panel, and changes take effect
without a deploy.

**This is the highest-privilege surface in the package.** Anyone who can write
its table can run whatever the PHP process can. Guard the editor with a strong
ability; `command-center:check` fails outright if the source is enabled while
that gate is undefined.

The editor is structured — no raw JSON field — because a free-text box would let
the writer smuggle keys past the form. Variables are edited as repeatable rows;
the form stores them keyed by name, which is the shape the parser expects.

## Custom sources

Implement the contract and register the class:

```php
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Sources\CommandSource;

final class YamlSource implements CommandSource
{
    /** @return array<string, CommandDefinition> */
    public function definitions(): array
    {
        // Build definitions however you like, then hand them back keyed by key.
    }
}
```

Resolved from the container, so constructor injection works. Use
`ArrayDefinitionParser` if your format matches the config array shape — it
applies every validation the config source gets.

## Precedence and caching

Sources are merged in the order they are listed, and a later source overrides an
earlier one for the same key. `CommandRegistry` memoises per container scope;
`flush()` re-queries the sources. Note that a `ConfigSource` freezes its array at
construction, so changing config at runtime needs the scope rebuilt, not just a
flush.
