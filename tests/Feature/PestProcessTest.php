<?php

declare(strict_types=1);

use Pest\Bisect\Support\OrderedConfig;
use Pest\Bisect\Support\PestProcess;

beforeEach(function (): void {
    if (! is_file(fixturePath('vendor/bin/pest'))) {
        $this->markTestSkipped('Run "composer fixture:install" first.');
    }

    $this->process = new PestProcess(
        fixturePath('vendor/bin/pest'),
        fixturePath(),
        new OrderedConfig(fixturePath(), fixturePath('phpunit.xml')),
    );
});

it('runs a seeded random order and reports the failing test', function (): void {
    $junit = $this->process->seed(1);

    $failure = $junit->firstFailure();

    expect($failure)->not->toBeNull()
        ->and($failure['name'])->toBe('it requires a clean global state')
        ->and($failure['file'])->toBe('tests/AaaVictimTest.php')
        ->and($junit->orderedFiles())->toContain('tests/ZzzCulpritTest.php');
});

it('runs a seeded random order that passes', function (): void {
    expect($this->process->seed(3)->firstFailure())->toBeNull();
});

it('reproduces the failure only when the culprit runs before the victim', function (): void {
    $poisoned = $this->process->ordered([
        'tests/ZzzCulpritTest.php',
        'tests/AaaVictimTest.php',
    ]);

    $clean = $this->process->ordered([
        'tests/AaaVictimTest.php',
        'tests/ZzzCulpritTest.php',
    ]);

    expect($poisoned->hasFailure('it requires a clean global state', 'tests/AaaVictimTest.php'))->toBeTrue()
        ->and($clean->hasFailure('it requires a clean global state', 'tests/AaaVictimTest.php'))->toBeFalse();
});

it('cleans up its generated configuration', function (): void {
    $this->process->ordered(['tests/AaaVictimTest.php']);

    expect(is_file(fixturePath('.pest-bisect.xml')))->toBeFalse();
});
