<?php

declare(strict_types=1);

use Pest\Bisect\Support\OrderedConfig;

beforeEach(function (): void {
    $this->base = sys_get_temp_dir().'/bisect-cfg-'.uniqid();
    mkdir($this->base);
});

afterEach(function (): void {
    foreach (['/.pest-bisect.xml', '/phpunit.xml'] as $file) {
        if (is_file($this->base.$file)) {
            unlink($this->base.$file);
        }
    }

    if (is_dir($this->base)) {
        rmdir($this->base);
    }
});

it('writes the files as an ordered test suite', function (): void {
    $path = (new OrderedConfig($this->base, null))->write([
        'tests/BTest.php',
        'tests/ATest.php',
    ]);

    expect($path)->toBe($this->base.'/.pest-bisect.xml');

    $xml = simplexml_load_file($path);
    $files = array_map('strval', iterator_to_array($xml->testsuites->testsuite->file, false));

    expect($files)->toBe(['tests/BTest.php', 'tests/ATest.php'])
        ->and((string) $xml->testsuites->testsuite['name'])->toBe('bisect');
});

it('falls back to the vendor autoloader when there is no source config', function (): void {
    $path = (new OrderedConfig($this->base, null))->write(['tests/ATest.php']);

    $xml = simplexml_load_file($path);

    expect((string) $xml['bootstrap'])->toBe($this->base.'/vendor/autoload.php');
});

it('keeps the bootstrap and php block from the source config, dropping its suites', function (): void {
    $source = $this->base.'/phpunit.xml';
    file_put_contents($source, <<<'XML'
    <?xml version="1.0"?>
    <phpunit bootstrap="tests/bootstrap.php">
        <testsuites><testsuite name="original"><directory>tests</directory></testsuite></testsuites>
        <php><env name="APP_ENV" value="testing"/></php>
    </phpunit>
    XML);

    $path = (new OrderedConfig($this->base, $source))->write(['tests/ATest.php']);
    $xml = simplexml_load_file($path);

    expect((string) $xml['bootstrap'])->toBe('tests/bootstrap.php')
        ->and((string) $xml->php->env['name'])->toBe('APP_ENV')
        ->and($xml->testsuites->testsuite->count())->toBe(1)
        ->and((string) $xml->testsuites->testsuite['name'])->toBe('bisect');
});
