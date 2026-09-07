<?php

declare(strict_types=1);

namespace Pest\Bisect\Contracts;

use Pest\Bisect\Support\JUnit;

/**
 * @internal
 */
interface Runner
{
    /**
     * Runs the whole suite in a random order for the given seed.
     */
    public function seed(int $seed): JUnit;

    /**
     * Runs the given files in exactly the given order.
     *
     * @param  list<string>  $files
     */
    public function ordered(array $files): JUnit;
}
