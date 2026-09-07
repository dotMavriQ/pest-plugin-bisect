<?php

declare(strict_types=1);

namespace Pest\Bisect\Support;

/**
 * @internal
 */
final class Ddmin
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(list<T>): bool  $reproduces
     * @return list<T>
     */
    public static function minimize(array $items, callable $reproduces): array
    {
        $granularity = 2;

        while (count($items) >= 2) {
            $chunks = self::chunk($items, min($granularity, count($items)));

            $reducedToChunk = null;
            foreach ($chunks as $chunk) {
                if ($reproduces($chunk)) {
                    $reducedToChunk = $chunk;
                    break;
                }
            }

            if ($reducedToChunk !== null) {
                $items = $reducedToChunk;
                $granularity = 2;

                continue;
            }

            $reducedComplement = null;
            foreach ($chunks as $index => $chunk) {
                $complement = self::complement($chunks, $index);

                if ($complement !== [] && $reproduces($complement)) {
                    $reducedComplement = $complement;
                    break;
                }
            }

            if ($reducedComplement !== null) {
                $items = $reducedComplement;
                $granularity = max($granularity - 1, 2);

                continue;
            }

            if ($granularity >= count($items)) {
                break;
            }

            $granularity = min(count($items), $granularity * 2);
        }

        return $items;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<list<T>>
     */
    private static function chunk(array $items, int $count): array
    {
        $count = max(1, min($count, count($items)));
        $size = (int) ceil(count($items) / $count);

        return array_chunk($items, max(1, $size));
    }

    /**
     * @template T
     *
     * @param  list<list<T>>  $chunks
     * @return list<T>
     */
    private static function complement(array $chunks, int $except): array
    {
        $result = [];

        foreach ($chunks as $index => $chunk) {
            if ($index === $except) {
                continue;
            }

            foreach ($chunk as $item) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
