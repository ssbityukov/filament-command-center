<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Exceptions\LockUnavailableException;
use Bityukov\CommandCenter\Execution\ConcurrencyLock;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Store;

function limited(?int $concurrency): CommandDefinition
{
    return Command::make('backup')->run('backup:run')->concurrency($concurrency)->toDefinition(30);
}

it('lets an unlimited command through without locking', function (): void {
    $lock = app(ConcurrencyLock::class);

    expect($lock->acquire(limited(null)))->not->toBeNull()
        ->and($lock->acquire(limited(null)))->not->toBeNull();
});

it('lets the first caller through when concurrency is one', function (): void {
    expect(app(ConcurrencyLock::class)->acquire(limited(1)))->not->toBeNull();
});

it('refuses a second caller while the first holds the lock', function (): void {
    $lock = app(ConcurrencyLock::class);

    $lock->acquire(limited(1));

    expect($lock->acquire(limited(1)))->toBeNull();
});

it('lets a second caller through after release', function (): void {
    $lock = app(ConcurrencyLock::class);

    $owner = $lock->acquire(limited(1));
    $lock->release(limited(1), (string) $owner);

    expect($lock->acquire(limited(1)))->not->toBeNull();
});

it('allows as many simultaneous runs as the limit permits', function (): void {
    $lock = app(ConcurrencyLock::class);

    expect($lock->acquire(limited(2)))->not->toBeNull()
        ->and($lock->acquire(limited(2)))->not->toBeNull()
        ->and($lock->acquire(limited(2)))->toBeNull();
});

it('locks per command key rather than globally', function (): void {
    $lock = app(ConcurrencyLock::class);

    $lock->acquire(limited(1));

    $other = Command::make('other')->run('other:run')->concurrency(1)->toDefinition(30);

    expect($lock->acquire($other))->not->toBeNull();
});

it('ignores a release with the wrong owner token', function (): void {
    $lock = app(ConcurrencyLock::class);

    $lock->acquire(limited(1));
    $lock->release(limited(1), '1:not-the-owner');

    expect($lock->acquire(limited(1)))->toBeNull();
});

it('fails closed when the cache store cannot take a lock', function (): void {
    // A store without LockProvider: treating that as "no limit" would turn the
    // guard into decoration on exactly the drivers that lack it.
    $store = new class implements Store
    {
        public function get($key) {}

        public function many(array $keys)
        {
            return [];
        }

        public function put($key, $value, $seconds)
        {
            return true;
        }

        public function putMany(array $values, $seconds)
        {
            return true;
        }

        public function increment($key, $value = 1)
        {
            return 1;
        }

        public function decrement($key, $value = 1)
        {
            return 1;
        }

        public function forever($key, $value)
        {
            return true;
        }

        public function forget($key)
        {
            return true;
        }

        public function flush()
        {
            return true;
        }

        public function getPrefix()
        {
            return '';
        }

        public function touch($key, $seconds)
        {
            return true;
        }
    };

    $repository = new Repository($store);

    $factory = new class($repository) implements Factory
    {
        public function __construct(private $repository) {}

        public function store($name = null)
        {
            return $this->repository;
        }
    };

    expect(fn () => (new ConcurrencyLock($factory))->acquire(limited(1)))
        ->toThrow(LockUnavailableException::class, 'atomic locks');
});
