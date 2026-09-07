<?php

declare(strict_types=1);

namespace Pest\Bisect\Support;

use RuntimeException;
use SimpleXMLElement;

/**
 * @internal
 *
 * @phpstan-type TestCaseResult array{name: string, class: string, file: string, failed: bool}
 */
final class JUnit
{
    /**
     * @param  list<TestCaseResult>  $testCases
     */
    public function __construct(public readonly array $testCases) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("The JUnit report [$path] was not created.");
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException("The JUnit report [$path] is empty.");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException("The JUnit report [$path] is not valid XML.");
        }

        $testCases = [];
        self::walk($xml, $testCases);

        return new self($testCases);
    }

    /**
     * @param  list<TestCaseResult>  $testCases
     */
    private static function walk(SimpleXMLElement $node, array &$testCases): void
    {
        foreach ($node->children() as $child) {
            if ($child->getName() === 'testsuite') {
                self::walk($child, $testCases);

                continue;
            }

            if ($child->getName() !== 'testcase') {
                continue;
            }

            $failed = false;
            foreach ($child->children() as $grandChild) {
                if (in_array($grandChild->getName(), ['failure', 'error'], true)) {
                    $failed = true;

                    break;
                }
            }

            $file = (string) ($child['file'] ?? '');
            if (str_contains($file, '::')) {
                $file = substr($file, 0, (int) strpos($file, '::'));
            }

            $testCases[] = [
                'name' => (string) ($child['name'] ?? ''),
                'class' => (string) ($child['class'] ?? ''),
                'file' => $file,
                'failed' => $failed,
            ];
        }
    }

    /**
     * The test files in execution order, de-duplicated, keeping the first occurrence.
     *
     * @return list<string>
     */
    public function orderedFiles(): array
    {
        $files = [];

        foreach ($this->testCases as $testCase) {
            if ($testCase['file'] !== '' && ! in_array($testCase['file'], $files, true)) {
                $files[] = $testCase['file'];
            }
        }

        return $files;
    }

    /**
     * @return TestCaseResult|null
     */
    public function firstFailure(): ?array
    {
        foreach ($this->testCases as $testCase) {
            if ($testCase['failed']) {
                return $testCase;
            }
        }

        return null;
    }

    public function hasFailure(string $name, string $file): bool
    {
        foreach ($this->testCases as $testCase) {
            if ($testCase['failed'] && $testCase['name'] === $name && $testCase['file'] === $file) {
                return true;
            }
        }

        return false;
    }
}
