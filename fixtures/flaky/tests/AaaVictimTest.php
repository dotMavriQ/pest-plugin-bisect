<?php

// Named to sort first so the suite passes in default order. It only fails when a
// shuffle puts ZzzCulpritTest before it.

it('requires a clean global state', function (): void {
    expect($GLOBALS['__bisect_poison'] ?? null)->toBeNull();
});
