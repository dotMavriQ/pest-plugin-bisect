<?php

declare(strict_types=1);

use Pest\Bisect\Support\Printer;
use Symfony\Component\Console\Output\BufferedOutput;

function printer(): array
{
    $output = new BufferedOutput;

    return [new Printer($output), $output];
}

it('does not print step progress to a non-decorated output', function (): void {
    [$printer, $output] = printer();

    $printer->step(3, 12);

    expect($output->fetch())->toBe('');
});

it('renders the result with culprits, the victim marker and a replay hint', function (): void {
    [$printer, $output] = printer();

    $printer->result(
        seed: 4321,
        victimName: 'it charges the card',
        before: 40,
        order: ['tests/AaTest.php', 'tests/BbTest.php', 'tests/VictimTest.php'],
    );

    $text = $output->fetch();

    expect($text)
        ->toContain('Narrowed 41 files down to 3')
        ->toContain('1  tests/AaTest.php')
        ->toContain('2  tests/BbTest.php')
        ->toContain('3  tests/VictimTest.php  ← it charges the card')
        ->toContain('pest --bisect=4321');
});

it('states clearly when nothing reproduces', function (): void {
    [$printer, $output] = printer();

    $printer->cannotReproduce();

    expect($output->fetch())->toContain('Could not reproduce the failure at file granularity');
});

it('states clearly when the suite passed for a given seed', function (): void {
    [$printer, $output] = printer();

    $printer->seedPassed(99);

    expect($output->fetch())->toContain('The suite passed with seed 99');
});

it('announces the search and the seed it lands on', function (): void {
    [$printer, $output] = printer();

    $printer->searching(30);
    $printer->seedFound(51234, 4);

    expect($output->fetch())
        ->toContain('Searching for a failing order (up to 30 attempts)')
        ->toContain('seed 51234')
        ->toContain('attempt 4');
});

it('reports when no random order failed', function (): void {
    [$printer, $output] = printer();

    $printer->noFailure(30);

    expect($output->fetch())->toContain('No order-dependent failure in 30 random orders');
});

it('names the victim before it starts bisecting', function (): void {
    [$printer, $output] = printer();

    $printer->bisecting(12, 'it works alone (tests/T.php)', 1);

    expect($output->fetch())
        ->toContain('Seed')
        ->toContain('12')
        ->toContain('it works alone (tests/T.php)')
        ->toContain('1 file');
});

it('explains a test that fails on its own', function (): void {
    [$printer, $output] = printer();

    $printer->failsOnItsOwn('it is just broken (tests/T.php)');

    expect($output->fetch())
        ->toContain('Not an order dependency')
        ->toContain('it is just broken (tests/T.php)');
});

it('writes step progress to a decorated output', function (): void {
    $output = new class extends BufferedOutput
    {
        public function isDecorated(): bool
        {
            return true;
        }
    };

    new Printer($output)->step(2, 5);

    expect($output->fetch())->toContain('run 2')->toContain('5 files left');
});
