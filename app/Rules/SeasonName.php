<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A season is named for one year — "2026" — or for two consecutive ones — "2026/27" or
 * "2026/2027".
 *
 * A rule rather than a regex because the second half is arithmetic, not shape. "2026/29"
 * matches every pattern anybody would write and is still a typo, and a season name is the
 * kind of thing that gets typed once and then read for years.
 */
class SeasonName implements ValidationRule
{
    private const SHAPE = '/^(\d{4})(?:\/(\d{2}|\d{4}))?$/';

    /**
     * @param  Closure(string, string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Emptiness is `required`'s business, not this rule's. A rule that enforces two
        // things is a rule whose message can only describe one of them.
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (preg_match(self::SHAPE, trim($value), $matches) !== 1) {
            $fail('Name a season for one year ("2026") or two ("2026/27").');

            return;
        }

        if (! isset($matches[2])) {
            return;
        }

        $first = (int) $matches[1];
        $second = $this->secondYear($first, $matches[2]);

        if ($second !== $first + 1) {
            $fail(sprintf(
                'The second year has to follow the first: "%s", not "%s".',
                sprintf('%d/%02d', $first, ($first + 1) % 100),
                trim($value),
            ));
        }
    }

    /**
     * "2026/27" is 2027. "2099/00" is 2100, not 2000 — the century rolls over, and a
     * two-digit year that lands before the first one has to be the next century.
     */
    private function secondYear(int $first, string $suffix): int
    {
        if (strlen($suffix) === 4) {
            return (int) $suffix;
        }

        $candidate = intdiv($first, 100) * 100 + (int) $suffix;

        return $candidate < $first ? $candidate + 100 : $candidate;
    }
}
