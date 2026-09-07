<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Pest\Bisect\Contracts\Runner;
use Pest\Bisect\Support\JUnit;

/**
 * An in-memory Runner for exercising the Bisector without spawning processes.
 */
final class FakeRunner implements Runner
{
    /** @var list<int> */
    public array $seedRuns = [];

    /** @var list<list<string>> */
    public array $orderedRuns = [];

    /**
     * @param  list<string>  $failingOrder  execution order for the failing seed
     * @param  list<string>  $poison  files that, together with the victim, reproduce; [] means the victim fails whenever it runs
     * @param  array<int, list<string>>  $seedResults  seed => execution order; [] means that seed passes
     * @param  bool  $orderedReproduces  when false, ordered() never reproduces (models a sub-file or non-recreatable dependency)
     */
    public function __construct(
        private readonly array $failingOrder,
        private readonly string $victimName,
        private readonly string $victimFile,
        private readonly array $poison = [],
        private readonly array $seedResults = [],
        private readonly bool $orderedReproduces = true,
    ) {}

    public function seed(int $seed): JUnit
    {
        $this->seedRuns[] = $seed;

        $order = array_key_exists($seed, $this->seedResults) ? $this->seedResults[$seed] : $this->failingOrder;

        return $this->report($order, poisoned: $order !== []);
    }

    /**
     * @param  list<string>  $files
     */
    public function ordered(array $files): JUnit
    {
        $this->orderedRuns[] = $files;

        return $this->report($files, poisoned: $this->orderedReproduces && $this->victimFails($files));
    }

    /**
     * @param  list<string>  $files
     */
    private function victimFails(array $files): bool
    {
        if (! in_array($this->victimFile, $files, true)) {
            return false;
        }

        foreach ($this->poison as $needle) {
            if (! in_array($needle, $files, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $order
     */
    private function report(array $order, bool $poisoned): JUnit
    {
        $cases = [];

        foreach ($order as $file) {
            $isVictim = $file === $this->victimFile;

            $cases[] = [
                'name' => $isVictim ? $this->victimName : 'it passes',
                'class' => '',
                'file' => $file,
                'failed' => $isVictim && $poisoned,
            ];
        }

        return new JUnit($cases);
    }
}
