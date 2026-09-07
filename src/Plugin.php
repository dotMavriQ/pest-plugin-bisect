<?php

declare(strict_types=1);

namespace Pest\Bisect;

use Pest\Bisect\Support\JUnit;
use Pest\Bisect\Support\OrderedConfig;
use Pest\Bisect\Support\PestProcess;
use Pest\Bisect\Support\Printer;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements AddsOutput, HandlesArguments
{
    use HandleArguments;

    private const DEFAULT_ATTEMPTS = 25;

    private bool $enabled = false;

    private ?int $explicitSeed = null;

    private int $probeSeed = 0;

    private int $attempts = self::DEFAULT_ATTEMPTS;

    private string $reportPath = '';

    public function __construct(private readonly OutputInterface $output) {}

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--bisect', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--parallel', $arguments) || $this->hasArgument('-p', $arguments)) {
            throw new InvalidOption('The [--bisect] option is not supported when running in parallel.');
        }

        $seed = $this->stripOption('--bisect', $arguments);
        $attempts = $this->stripOption('--bisect-attempts', $arguments);
        $existingSeed = $this->stripOption('--random-order-seed', $arguments);
        $orderBy = $this->stripOption('--order-by', $arguments);
        $this->stripOption('--log-junit', $arguments);

        if ($orderBy !== null && $orderBy !== 'random') {
            throw new InvalidOption('The [--bisect] option always runs in a random order.');
        }

        $seed ??= $existingSeed;

        if ($seed !== null) {
            if (! ctype_digit($seed)) {
                throw new InvalidOption('The [--bisect] seed must be a positive integer.');
            }

            $this->explicitSeed = (int) $seed;
        }

        if ($attempts !== null && ctype_digit($attempts) && (int) $attempts > 0) {
            $this->attempts = (int) $attempts;
        }

        $this->enabled = true;
        $this->probeSeed = $this->explicitSeed ?? random_int(1, 999_999);
        $this->reportPath = (string) tempnam(sys_get_temp_dir(), 'pest-bisect-probe-');

        $arguments = $this->pushArgument('--order-by=random', $arguments);
        $arguments = $this->pushArgument('--random-order-seed='.$this->probeSeed, $arguments);
        $arguments = $this->pushArgument('--log-junit='.$this->reportPath, $arguments);

        return $arguments;
    }

    public function addOutput(int $exitCode): int
    {
        if (! $this->enabled || Parallel::isWorker()) {
            return $exitCode;
        }

        $argv = $_SERVER['argv'] ?? [];
        $binary = is_array($argv) && isset($argv[0]) && is_string($argv[0])
            ? $argv[0]
            : 'vendor/bin/pest';

        return $this->bisect(TestSuite::getInstance()->rootPath, $binary);
    }

    /**
     * @internal Exposed for testing the orchestration without a full Pest run.
     */
    public function bisect(string $rootPath, string $binary): int
    {
        $probe = is_file($this->reportPath) && filesize($this->reportPath) > 0
            ? JUnit::fromFile($this->reportPath)
            : null;

        if ($this->reportPath !== '' && is_file($this->reportPath)) {
            unlink($this->reportPath);
        }

        $bisector = new Bisector(
            new PestProcess(
                $binary,
                $rootPath,
                new OrderedConfig($rootPath, $this->locateConfig($rootPath)),
            ),
            new Printer($this->output),
        );

        return $bisector->run($this->explicitSeed, $this->attempts, $this->probeSeed, $probe);
    }

    /**
     * Removes every occurrence of the given option from the arguments,
     * returning the inline value of the last "--option=value" form seen.
     *
     * @param  array<int, string>  $arguments
     */
    private function stripOption(string $option, array &$arguments): ?string
    {
        $value = null;
        $kept = [];

        foreach ($arguments as $argument) {
            if ($argument === $option) {
                continue;
            }

            if (str_starts_with($argument, $option.'=')) {
                $value = substr($argument, strlen($option) + 1);

                continue;
            }

            $kept[] = $argument;
        }

        $arguments = $kept;

        return $value;
    }

    private function locateConfig(string $rootPath): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'] as $name) {
            $path = $rootPath.DIRECTORY_SEPARATOR.$name;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
