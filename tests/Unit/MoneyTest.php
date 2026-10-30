<?php

use App\Support\Money;

test('dollar input is converted to cents', function (mixed $input, ?int $cents) {
    expect(Money::toCents($input))->toBe($cents);
})->with([
    ['12.34', 1234],
    ['$1,200', 120000],
    [' 0.1 ', 10],
    [5, 500],
    ['', null],
    [null, null],
    ['abc', null],
]);
