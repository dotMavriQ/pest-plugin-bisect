<?php

declare(strict_types=1);

use Pest\Bisect\Bisector;
use Pest\Bisect\Support\JUnit;
use Pest\Bisect\Support\Printer;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\FakeRunner;

function bisect(FakeRunner $runner, ?int $seed = 7, int $attempts = 25, ?JUnit $probe = null): array
{
    $output = new BufferedOutput;
    $code = (new Bisector($runner, new Printer($output)))->run($seed, $attempts, $seed ?? 1, $probe);

    return [$code, $output->fetch()];
}

it('narrows a large candidate list to the single poisoning file', function (): void {
    $order = ['n1', 'n2', 'culprit', 'n3', 'n4', 'n5', 'n6', 'n7', 'victim'];

    $runner = new FakeRunner($order, 'it needs clean state', 'victim', ['culprit']);

    [$code, $text] = bisect($runner, seed: 42);

    expect($code)->toBe(0)
        ->and($text)->toContain('Narrowed 9 files down to 2')
        ->and($text)->toContain('1  culprit')
        ->and($text)->toContain('2  victim')
        ->and(end($runner->orderedRuns))->toBe(['culprit', 'victim']);
});

it('keeps every file needed when two are required together, in order', function (): void {
    $order = ['a', 'setup', 'b', 'trigger', 'c', 'victim'];

    $runner = new FakeRunner($order, 'boom', 'victim', ['setup', 'trigger']);

    [$code, $text] = bisect($runner);

    expect($code)->toBe(0)
        ->and($text)->toContain('Narrowed 6 files down to 3')
        ->and($text)->toContain('1  setup')
        ->and($text)->toContain('2  trigger')
        ->and($text)->toContain('3  victim  ← boom');

    foreach ($runner->orderedRuns as $run) {
        expect(array_values($run))->toBe($run === [] ? [] : array_values(array_intersect($order, $run)));
    }
});

it('reports a test that fails on its own as not an order dependency', function (): void {
    $runner = new FakeRunner(['victim', 'a', 'b'], 'boom', 'victim', []);

    [$code, $text] = bisect($runner);

    expect($code)->toBe(1)
        ->and($text)->toContain('Not an order dependency')
        ->and($runner->orderedRuns)->toBe([]);
});

it('reports when the failure cannot be reproduced at file granularity', function (): void {
    $order = ['a', 'b', 'victim'];

    $runner = new FakeRunner($order, 'boom', 'victim', ['__never_present__']);

    [$code, $text] = bisect($runner);

    expect($code)->toBe(1)
        ->and($text)->toContain('Could not reproduce');
});

it('says nothing to bisect when the seed produces a passing run', function (): void {
    $runner = new FakeRunner([], 'boom', 'victim', ['culprit'], seedResults: [9 => []]);

    [$code, $text] = bisect($runner, seed: 9);

    expect($code)->toBe(0)
        ->and($text)->toContain('passed with seed 9');
});

it('searches random seeds until one fails when no seed is given', function (): void {
    $order = ['culprit', 'victim'];

    $runner = new FakeRunner($order, 'boom', 'victim', ['culprit']);

    $passingProbe = new JUnit([
        ['name' => 'it passes', 'class' => '', 'file' => 'victim', 'failed' => false],
    ]);

    [$code, $text] = bisect($runner, seed: null, attempts: 10, probe: $passingProbe);

    expect($code)->toBe(0)
        ->and($text)->toContain('Searching for a failing order')
        ->and($text)->toContain('Reproduced')
        ->and($runner->seedRuns)->not->toBeEmpty();
});

it('gives up after the attempt budget when no order fails', function (): void {
    $runner = new FakeRunner([], 'boom', 'victim', ['culprit']);

    [$code, $text] = bisect($runner, seed: null, attempts: 5);

    expect($code)->toBe(0)
        ->and($text)->toContain('No order-dependent failure in 5 random orders')
        ->and($runner->seedRuns)->toHaveCount(5);
});
