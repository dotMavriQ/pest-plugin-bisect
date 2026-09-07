<?php

declare(strict_types=1);

use Pest\Bisect\Support\Ddmin;

it('returns the input when it is already minimal', function (): void {
    $result = Ddmin::minimize(['a'], fn (array $s): bool => true);

    expect($result)->toBe(['a']);
});

it('reduces to the single element that reproduces', function (): void {
    $items = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

    $result = Ddmin::minimize($items, fn (array $subset): bool => in_array('e', $subset, true));

    expect($result)->toBe(['e']);
});

it('keeps a minimal pair and preserves order', function (): void {
    $items = ['a', 'b', 'c', 'd', 'e', 'f'];

    $reproduces = fn (array $subset): bool => in_array('b', $subset, true) && in_array('e', $subset, true);

    $result = Ddmin::minimize($items, $reproduces);

    expect($result)->toBe(['b', 'e']);
});

it('never returns a subset that does not reproduce', function (): void {
    $items = range(1, 40);

    $reproduces = fn (array $subset): bool => array_sum($subset) >= 100;

    $result = Ddmin::minimize($items, $reproduces);

    expect($reproduces($result))->toBeTrue()
        ->and($result)->toBe(array_values($result));
});

it('handles an oracle that always fails by returning the full input', function (): void {
    $items = ['a', 'b', 'c'];

    $result = Ddmin::minimize($items, fn (array $s): bool => false);

    expect($result)->toBe(['a', 'b', 'c']);
});
