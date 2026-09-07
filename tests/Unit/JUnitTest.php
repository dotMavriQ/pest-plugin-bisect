<?php

declare(strict_types=1);

use Pest\Bisect\Support\JUnit;

function writeJUnit(string $xml): string
{
    $path = tempnam(sys_get_temp_dir(), 'junit-test-').'.xml';
    file_put_contents($path, $xml);

    return $path;
}

const SAMPLE_JUNIT = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="root">
    <testsuite name="default">
      <testsuite name="Tests\NoiseOneTest" file="tests/NoiseOneTest.php">
        <testcase name="it does one" file="tests/NoiseOneTest.php::it does one" class="Tests\NoiseOneTest"/>
      </testsuite>
      <testsuite name="Tests\ZzzCulpritTest" file="tests/ZzzCulpritTest.php">
        <testcase name="it leaves state behind" file="tests/ZzzCulpritTest.php::it leaves state behind" class="Tests\ZzzCulpritTest"/>
      </testsuite>
      <testsuite name="Tests\AaaVictimTest" file="tests/AaaVictimTest.php">
        <testcase name="it requires a clean global state" file="tests/AaaVictimTest.php::it requires a clean global state" class="Tests\AaaVictimTest">
          <failure type="PHPUnit\Framework\ExpectationFailedException">Failed asserting that 'tainted' is null.</failure>
        </testcase>
      </testsuite>
    </testsuite>
  </testsuite>
</testsuites>
XML;

it('reads test cases in execution order', function (): void {
    $junit = JUnit::fromFile(writeJUnit(SAMPLE_JUNIT));

    expect($junit->orderedFiles())->toBe([
        'tests/NoiseOneTest.php',
        'tests/ZzzCulpritTest.php',
        'tests/AaaVictimTest.php',
    ]);
});

it('finds the first failing test', function (): void {
    $junit = JUnit::fromFile(writeJUnit(SAMPLE_JUNIT));

    expect($junit->firstFailure())->toMatchArray([
        'name' => 'it requires a clean global state',
        'file' => 'tests/AaaVictimTest.php',
        'failed' => true,
    ]);
});

it('reports whether a specific test failed', function (): void {
    $junit = JUnit::fromFile(writeJUnit(SAMPLE_JUNIT));

    expect($junit->hasFailure('it requires a clean global state', 'tests/AaaVictimTest.php'))->toBeTrue()
        ->and($junit->hasFailure('it does one', 'tests/NoiseOneTest.php'))->toBeFalse();
});

it('treats errors as failures', function (): void {
    $xml = str_replace(['<failure', '</failure>'], ['<error', '</error>'], SAMPLE_JUNIT);
    $junit = JUnit::fromFile(writeJUnit($xml));

    expect($junit->firstFailure())->not->toBeNull();
});

it('throws when the report is missing', function (): void {
    JUnit::fromFile('/does/not/exist.xml');
})->throws(RuntimeException::class, 'was not created');

it('throws when the report is empty', function (): void {
    JUnit::fromFile(writeJUnit('   '));
})->throws(RuntimeException::class, 'is empty');

it('throws when the report is not valid XML', function (): void {
    JUnit::fromFile(writeJUnit('<testsuites><broken'));
})->throws(RuntimeException::class, 'not valid XML');

it('returns no files and no failure for a run with no test cases', function (): void {
    $junit = JUnit::fromFile(writeJUnit('<?xml version="1.0"?><testsuites></testsuites>'));

    expect($junit->orderedFiles())->toBe([])
        ->and($junit->firstFailure())->toBeNull();
});
