<?php

// Named to sort last. Leaves a global set, which breaks AaaVictimTest if a
// shuffle runs this first.

it('leaves state behind', function (): void {
    $GLOBALS['__bisect_poison'] = 'tainted';
    expect(true)->toBeTrue();
});
