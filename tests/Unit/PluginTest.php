<?php

declare(strict_types=1);

use Pest\Bisect\Plugin;
use Pest\Exceptions\InvalidOption;
use Symfony\Component\Console\Output\BufferedOutput;

function plugin(): Plugin
{
    return new Plugin(new BufferedOutput);
}

it('leaves the arguments untouched when --bisect is absent', function (): void {
    $arguments = ['--filter', 'Foo', '--bail'];

    expect((plugin())->handleArguments($arguments))->toBe($arguments);
});

it('does not touch the exit code when it was not asked to bisect', function (): void {
    expect((plugin())->addOutput(2))->toBe(2);
});

it('takes over the run for --bisect', function (): void {
    $result = (plugin())->handleArguments(['--bisect']);

    expect($result)->not->toContain('--bisect')
        ->and(implode(' ', $result))
        ->toContain('--order-by=random')
        ->toContain('--random-order-seed=')
        ->toContain('--log-junit=');
});

it('accepts a seed through --bisect=<seed> and pins it', function (): void {
    $result = (plugin())->handleArguments(['--bisect=12345']);

    expect(implode(' ', $result))->toContain('--random-order-seed=12345');
});

it('accepts an attempt budget through --bisect-attempts', function (): void {
    $plugin = plugin();
    $plugin->handleArguments(['--bisect', '--bisect-attempts=7']);

    expect((new ReflectionProperty($plugin, 'attempts'))->getValue($plugin))->toBe(7);
});

it('ignores a non-positive attempt budget', function (): void {
    $plugin = plugin();
    $plugin->handleArguments(['--bisect', '--bisect-attempts=0']);

    expect((new ReflectionProperty($plugin, 'attempts'))->getValue($plugin))->toBe(25);
});

it('locates a phpunit config next to the project root, or nothing', function (): void {
    $locate = (new ReflectionMethod(Plugin::class, 'locateConfig'))->getClosure(plugin());

    $dir = sys_get_temp_dir().'/bisect-locate-'.uniqid();
    mkdir($dir);

    expect($locate($dir))->toBeNull();

    touch($dir.'/phpunit.xml');
    expect($locate($dir))->toBe($dir.'/phpunit.xml');

    unlink($dir.'/phpunit.xml');
    rmdir($dir);
});

it('reads the seed from an existing --random-order-seed', function (): void {
    $result = (plugin())->handleArguments(['--bisect', '--random-order-seed=777']);

    expect(implode(' ', $result))->toContain('--random-order-seed=777')
        ->and(array_count_values($result)['--random-order-seed=777'] ?? 0)->toBe(1);
});

it('rejects a non-numeric seed', function (): void {
    (plugin())->handleArguments(['--bisect=nope']);
})->throws(InvalidOption::class, 'positive integer');

it('rejects running in parallel', function (string $flag): void {
    (plugin())->handleArguments(['--bisect', $flag]);
})->with(['--parallel', '-p'])->throws(InvalidOption::class, 'parallel');

it('rejects a non-random order', function (): void {
    (plugin())->handleArguments(['--bisect', '--order-by=defects']);
})->throws(InvalidOption::class, 'random order');

it('allows --order-by=random to be passed explicitly', function (): void {
    $result = (plugin())->handleArguments(['--bisect', '--order-by=random']);

    expect(implode(' ', $result))->toContain('--order-by=random');
});
