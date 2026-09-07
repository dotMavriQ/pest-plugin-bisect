<?php

declare(strict_types=1);

namespace Pest\Bisect\Support;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * @internal
 */
final class OrderedConfig
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $sourceConfig,
    ) {}

    /**
     * Writes a PHPUnit configuration whose single test suite runs the given files
     * in exactly the given order, and returns its path.
     *
     * @param  list<string>  $files
     */
    public function write(array $files): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if ($this->sourceConfig !== null && is_file($this->sourceConfig)) {
            if (@$document->load($this->sourceConfig) === false) {
                throw new RuntimeException("Unable to read the PHPUnit configuration [{$this->sourceConfig}].");
            }
        }

        $phpunit = $document->documentElement;

        if (! $phpunit instanceof DOMElement) {
            $phpunit = $document->createElement('phpunit');
            $document->appendChild($phpunit);
        }

        if (! $phpunit->hasAttribute('bootstrap')) {
            $phpunit->setAttribute('bootstrap', $this->basePath.'/vendor/autoload.php');
        }

        foreach (iterator_to_array($phpunit->getElementsByTagName('testsuites')) as $existing) {
            $phpunit->removeChild($existing);
        }

        $testsuites = $document->createElement('testsuites');
        $testsuite = $document->createElement('testsuite');
        $testsuite->setAttribute('name', 'bisect');

        foreach ($files as $file) {
            $node = $document->createElement('file', $file);
            $testsuite->appendChild($node);
        }

        $testsuites->appendChild($testsuite);
        $phpunit->appendChild($testsuites);

        $path = $this->basePath.'/.pest-bisect.xml';

        if ($document->save($path) === false) {
            throw new RuntimeException("Unable to write the temporary configuration [$path].");
        }

        return $path;
    }
}
