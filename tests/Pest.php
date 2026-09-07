<?php

declare(strict_types=1);

function fixturePath(string $path = ''): string
{
    return dirname(__DIR__).'/fixtures/flaky'.($path === '' ? '' : '/'.ltrim($path, '/'));
}
