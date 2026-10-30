<?php

use App\Support\ChecklistParser;

test('checklist lines are parsed into numbers, names, teams and flags', function (string $line, array $expected) {
    $card = (new ChecklistParser)->parse($line)[0];

    expect(array_intersect_key($card, $expected))->toBe($expected);
})->with([
    'name and team' => ['1 Mike Trout - Los Angeles Angels', ['number' => '1', 'name' => 'Mike Trout', 'team' => 'Los Angeles Angels']],
    'hash number with rookie flag' => ['#US1 Shohei Ohtani RC', ['number' => 'US1', 'name' => 'Shohei Ohtani', 'is_rookie' => true]],
    'flags after team' => ['5 Wander Franco - Rays RC AU /99', ['team' => 'Rays', 'is_rookie' => true, 'is_autograph' => true, 'print_run' => 99]],
    'relic and short print' => ['T-10 Derek Jeter RELIC SSP', ['number' => 'T-10', 'name' => 'Derek Jeter', 'is_memorabilia' => true, 'is_short_print' => true]],
    'tab separated' => ["57\tMichael Jordan\tChicago Bulls\tRC", ['number' => '57', 'name' => 'Michael Jordan', 'team' => 'Chicago Bulls', 'is_rookie' => true]],
    'suffix kept in name' => ['12 Ken Griffey Jr.', ['name' => 'Ken Griffey Jr.', 'team' => null]],
]);

test('blank lines and lines without a name are skipped', function () {
    expect((new ChecklistParser)->parse("\n  \n42\n43 Real Card\n"))->toHaveCount(1);
});
