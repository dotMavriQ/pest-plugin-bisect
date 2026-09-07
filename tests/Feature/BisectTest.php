<?php

declare(strict_types=1);

use Pest\Bisect\Plugin;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

function runBisect(array $arguments): Process
{
    $process = new Process(
        [PHP_BINARY, '-d', 'memory_limit=-1', fixturePath('vendor/bin/pest'), ...$arguments],
        fixturePath(),
        ['XDEBUG_MODE' => 'off'],
        null,
        600,
    );

    $process->run();

    return $process;
}

beforeEach(function (): void {
    if (! is_file(fixturePath('vendor/bin/pest'))) {
        $this->markTestSkipped('Run "composer fixture:install" first.');
    }
});

it('bisects a planted order dependency down to the culprit', function (): void {
    $process = runBisect(['--bisect=1']);

    $output = $process->getOutput().$process->getErrorOutput();

    expect($output)
        ->toContain('BISECT')
        ->toContain('ZzzCulpritTest.php')
        ->toContain('AaaVictimTest.php')
        ->toContain('requires a clean global state')
        ->toContain('pest --bisect=1');

    $report = substr($output, (int) strpos($output, 'reproduces the failure'));

    expect(strpos($report, 'ZzzCulpritTest.php'))
        ->toBeLessThan(strpos($report, 'AaaVictimTest.php'));
});

it('reports when a seed produces a passing order', function (): void {
    $process = runBisect(['--bisect=3']);

    $output = $process->getOutput().$process->getErrorOutput();

    expect($output)->toContain('BISECT')
        ->and($output)->toMatch('/passed|Nothing to bisect/i');
});

it('finds a failing seed on its own when none is given', function (): void {
    $process = runBisect(['--bisect', '--bisect-attempts=100']);

    $output = $process->getOutput().$process->getErrorOutput();

    $report = substr($output, (int) strpos($output, 'reproduces the failure'));

    expect($output)->toContain('Narrowed')
        ->and(strpos($report, 'ZzzCulpritTest.php'))
        ->toBeLessThan(strpos($report, 'AaaVictimTest.php'));
});

it('drives the bisect end to end when the plugin is called directly', function (): void {
    $plugin = new Plugin($output = new BufferedOutput);
    $plugin->handleArguments(['--bisect=1']);

    $code = $plugin->bisect(fixturePath(), fixturePath('vendor/bin/pest'));

    expect($code)->toBe(0)
        ->and($output->fetch())
        ->toContain('Narrowed 6 files down to 2')
        ->toContain('tests/ZzzCulpritTest.php')
        ->toContain('tests/AaaVictimTest.php');
});

it('reuses the report the initial run already wrote instead of running the seed again', function (): void {
    $plugin = new Plugin($output = new BufferedOutput);
    $plugin->handleArguments(['--bisect=1']);

    $reportPath = (new ReflectionProperty($plugin, 'reportPath'))->getValue($plugin);
    file_put_contents($reportPath, <<<'XML'
    <?xml version="1.0"?>
    <testsuites><testsuite name="probe">
      <testcase name="it leaves state behind" file="tests/ZzzCulpritTest.php::it leaves state behind" class="Tests\ZzzCulpritTest"/>
      <testcase name="it does something unrelated One" file="tests/NoiseOneTest.php::it does something unrelated One" class="Tests\NoiseOneTest"/>
      <testcase name="it requires a clean global state" file="tests/AaaVictimTest.php::it requires a clean global state" class="Tests\AaaVictimTest">
        <failure type="PHPUnit\Framework\ExpectationFailedException">Failed asserting that 'tainted' is null.</failure>
      </testcase>
    </testsuite></testsuites>
    XML);

    $code = $plugin->bisect(fixturePath(), fixturePath('vendor/bin/pest'));

    expect($code)->toBe(0)
        ->and($output->fetch())->toContain('Narrowed 3 files down to 2')
        ->and(is_file($reportPath))->toBeFalse();
});
