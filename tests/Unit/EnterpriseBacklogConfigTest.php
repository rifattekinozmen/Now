<?php

test('enterprise backlog config is a non-empty ordered list', function () {
    $items = config('enterprise_backlog');

    expect($items)->toBeArray()->not->toBeEmpty()
        ->and($items[0])->toBeString();
});
