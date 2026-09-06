<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a name into a URL-safe slug, and keeps trying until it finds a free one.
 *
 * The language matters more than it looks. Transliterating with the generic table mangles
 * Polish club names — "Łódź" loses characters instead of becoming "lodz" — and that is
 * exactly the kind of thing nobody notices until real seed data arrives.
 *
 * Uniqueness is settled by suffixing rather than by rejecting the request. Two organizations
 * may legitimately be called the same thing, and somebody who never typed a slug should not
 * be shown an error about one.
 */
class SlugGenerator
{
    public const MAX_LENGTH = 64;

    public function slugify(string $value): string
    {
        return Str::limit(Str::slug($value, '-', 'pl'), self::MAX_LENGTH, '');
    }

    /**
     * @param  callable(string): bool  $isTaken
     */
    public function uniqueSlug(string $value, callable $isTaken, int $maxLength = self::MAX_LENGTH): string
    {
        $base = $this->slugify($value);

        if ($base === '') {
            $base = 'item';
        }

        $base = substr($base, 0, $maxLength);

        if (! $isTaken($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $tail = '-'.$suffix;
            $candidate = substr($base, 0, $maxLength - strlen($tail)).$tail;

            if (! $isTaken($candidate)) {
                return $candidate;
            }
        }

        // A thousand organizations with the same name is not a case worth designing for, but
        // silently returning a duplicate would hit the unique index as a 500.
        throw new RuntimeException(sprintf('Could not find a free slug based on "%s".', $value));
    }
}
