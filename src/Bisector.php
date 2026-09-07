<?php

declare(strict_types=1);

namespace Pest\Bisect;

use Pest\Bisect\Contracts\Runner;
use Pest\Bisect\Support\Ddmin;
use Pest\Bisect\Support\JUnit;
use Pest\Bisect\Support\Printer;

/**
 * @internal
 */
final class Bisector
{
    private int $runs = 0;

    private string $victimName = '';

    private string $victimFile = '';

    public function __construct(
        private readonly Runner $process,
        private readonly Printer $printer,
    ) {}

    public function run(?int $explicitSeed, int $attempts, int $probeSeed, ?JUnit $probeResult): int
    {
        [$seed, $junit] = $this->resolveFailingOrder($explicitSeed, $attempts, $probeSeed, $probeResult);

        if ($seed === null || $junit === null) {
            return 0;
        }

        $failure = $junit->firstFailure();

        if ($failure === null) {
            $this->printer->seedPassed($seed);

            return 0;
        }

        $this->victimName = $failure['name'];
        $this->victimFile = $failure['file'];

        $orderedFiles = $junit->orderedFiles();
        $victimIndex = array_search($this->victimFile, $orderedFiles, true);

        if ($victimIndex === false || $victimIndex === 0) {
            $this->printer->failsOnItsOwn($this->describeVictim());

            return 1;
        }

        $candidates = array_slice($orderedFiles, 0, $victimIndex);

        $this->printer->bisecting($seed, $this->describeVictim(), count($candidates));

        if (! $this->reproduces($candidates)) {
            $this->printer->cannotReproduce();

            return 1;
        }

        $minimal = Ddmin::minimize(
            $candidates,
            fn (array $subset): bool => $this->reproduces($subset),
        );

        $order = [...$minimal, $this->victimFile];

        $this->printer->result($seed, $this->victimName, count($candidates), $order);

        return 0;
    }

    /**
     * @return array{0: int|null, 1: JUnit|null}
     */
    private function resolveFailingOrder(?int $explicitSeed, int $attempts, int $probeSeed, ?JUnit $probeResult): array
    {
        if ($probeResult !== null && $probeResult->firstFailure() !== null) {
            return [$probeSeed, $probeResult];
        }

        if ($explicitSeed !== null) {
            return [$explicitSeed, $probeResult ?? $this->process->seed($explicitSeed)];
        }

        $this->printer->searching($attempts);

        $tried = $probeResult !== null ? 1 : 0;

        for ($attempt = $tried + 1; $attempt <= $attempts; $attempt++) {
            $seed = random_int(1, 999_999);
            $junit = $this->process->seed($seed);

            if ($junit->firstFailure() !== null) {
                $this->printer->seedFound($seed, $attempt);

                return [$seed, $junit];
            }
        }

        $this->printer->noFailure($attempts);

        return [null, null];
    }

    /**
     * @param  list<string>  $files
     */
    private function reproduces(array $files): bool
    {
        $this->runs++;
        $this->printer->step($this->runs, count($files));

        $junit = $this->process->ordered([...$files, $this->victimFile]);

        return $junit->hasFailure($this->victimName, $this->victimFile);
    }

    private function describeVictim(): string
    {
        return trim(sprintf('%s (%s)', $this->victimName, $this->victimFile));
    }
}
