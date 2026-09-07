<?php

declare(strict_types=1);

namespace Pest\Bisect\Support;

use Pest\Bisect\Contracts\Runner;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class PestProcess implements Runner
{
    private const TIMEOUT = 600;

    public function __construct(
        private readonly string $binary,
        private readonly string $basePath,
        private readonly OrderedConfig $config,
    ) {}

    public function seed(int $seed): JUnit
    {
        return $this->run([
            '--order-by=random',
            '--random-order-seed='.$seed,
        ]);
    }

    /**
     * @param  list<string>  $files
     */
    public function ordered(array $files): JUnit
    {
        $path = $this->config->write($files);

        try {
            return $this->run([
                '--configuration='.$path,
                '--order-by=default',
            ]);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments): JUnit
    {
        $report = tempnam(sys_get_temp_dir(), 'pest-bisect-').'.xml';

        $command = [
            PHP_BINARY,
            '-d',
            'memory_limit=-1',
            $this->binary,
            '--log-junit='.$report,
            '--colors=never',
            '--no-coverage',
            ...$arguments,
        ];

        $process = new Process($command, $this->basePath, $this->environment(), null, self::TIMEOUT);
        $process->run();

        try {
            return JUnit::fromFile($report);
        } finally {
            @unlink($report);
        }
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return [
            'PEST_BISECT_WORKER' => '1',
            'XDEBUG_MODE' => 'off',
        ];
    }
}
