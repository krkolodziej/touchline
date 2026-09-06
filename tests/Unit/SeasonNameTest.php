<?php

declare(strict_types=1);

use App\Rules\SeasonName;
use Illuminate\Support\Facades\Validator;

/** @return list<string> */
function seasonNameErrors(string $value): array
{
    $validator = Validator::make(['name' => $value], ['name' => [new SeasonName]]);

    /** @var list<string> $messages */
    $messages = $validator->errors()->get('name');

    return $messages;
}

it('accepts a single year', function (): void {
    expect(seasonNameErrors('2026'))->toBe([]);
});

it('accepts two consecutive years, in either form', function (string $value): void {
    expect(seasonNameErrors($value))->toBe([]);
})->with(['2026/27', '2026/2027', '1999/00', '1999/2000']);

/**
 * The reason this is a rule and not a regex. "2026/29" matches every pattern anybody would
 * write for a season name, and is still a typo — and a season name gets typed once and then
 * read for years.
 */
it('refuses a second year that does not follow the first', function (string $value): void {
    expect(seasonNameErrors($value))
        ->toBe(['The second year has to follow the first: "2026/27", not "'.$value.'".']);
})->with(['2026/29', '2026/25', '2026/2028', '2026/26']);

it('handles the century rolling over', function (): void {
    // 2099/00 is 2100, not 2000. A two-digit year that lands before the first one has to be
    // the next century.
    expect(seasonNameErrors('2099/00'))->toBe([]);
    expect(seasonNameErrors('2099/01'))->not->toBe([]);
});

it('refuses anything that is not shaped like a season name', function (string $value): void {
    expect(seasonNameErrors($value))
        ->toBe(['Name a season for one year ("2026") or two ("2026/27").']);
})->with(['Spring', '26', '2026-27', '2026/2', '2026/', 'the 2026 season', '20267']);

/**
 * Emptiness is `required`'s business. A rule that enforces two things is a rule whose
 * message can only describe one of them.
 */
it('says nothing about an empty value', function (): void {
    expect(seasonNameErrors(''))->toBe([])
        ->and(seasonNameErrors('   '))->toBe([]);
});
