<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Execution\OutputBuffer;

it('starts empty', function (): void {
    expect(app(OutputBuffer::class)->all('run-1'))->toBe('')
        ->and(app(OutputBuffer::class)->length('run-1'))->toBe(0);
});

it('appends chunks in order', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'one ');
    $buffer->append('run-1', 'two');

    expect($buffer->all('run-1'))->toBe('one two');
});

it('keeps runs separate', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'a');
    $buffer->append('run-2', 'b');

    expect($buffer->all('run-1'))->toBe('a')
        ->and($buffer->all('run-2'))->toBe('b');
});

it('reads only the bytes past an offset', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'hello world');

    expect($buffer->read('run-1', offset: 6))->toBe('world')
        ->and($buffer->read('run-1', offset: 0))->toBe('hello world');
});

it('returns an empty string when the offset is past the end', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'hi');

    expect($buffer->read('run-1', offset: 99))->toBe('');
});

it('reports its length in bytes', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'abcde');

    expect($buffer->length('run-1'))->toBe(5);
});

/*
 | 200 bytes against a 100-byte cap. The cap has a 64-byte floor because the
 | truncation marker itself is about 24 bytes, so a smaller cap would produce
 | more marker than output.
 */
it('caps the buffer keeping the head and the tail', function (): void {
    config()->set('command-center.output.max_bytes', 100);

    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', str_repeat('A', 100));
    $buffer->append('run-1', str_repeat('B', 100));

    $all = $buffer->all('run-1');

    expect(strlen($all))->toBeLessThanOrEqual(200)
        ->and($all)->toStartWith('A')
        ->and($all)->toEndWith('B')
        ->and($all)->toContain('truncated');
});

it('does not grow without bound across many appends', function (): void {
    config()->set('command-center.output.max_bytes', 100);

    $buffer = app(OutputBuffer::class);

    foreach (range(1, 200) as $i) {
        $buffer->append('run-1', "line {$i}\n");
    }

    expect(strlen($buffer->all('run-1')))->toBeLessThan(400);
});

it('forgets a run', function (): void {
    $buffer = app(OutputBuffer::class);

    $buffer->append('run-1', 'gone');
    $buffer->forget('run-1');

    expect($buffer->all('run-1'))->toBe('');
});
