<?php

declare(strict_types=1);

namespace App\Support\Listing;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The four things every collection accepts, in one place.
 *
 * Paging is opt-in. Sending neither `page` nor `page_size` gives a plain array; sending
 * either switches to the envelope. That keeps the small collections which dominate this
 * application — twelve clubs, eighteen players — free of ceremony, while still giving a
 * long one a way to be walked.
 *
 * The bounds are checked here rather than trusted. `page_size=0` is a message, not a
 * division by zero further down; `page_size=100000` is a message, not a query that reads the
 * whole table because somebody edited a URL.
 */
readonly class ListQuery
{
    public const DEFAULT_PAGE_SIZE = 20;

    public const MAX_PAGE_SIZE = 100;

    public function __construct(
        public string $search = '',
        public ?int $page = null,
        public ?int $pageSize = null,
        public ?string $order = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $page = self::readInt($request, 'page');
        $pageSize = self::readInt($request, 'page_size');

        if ($page !== null && $page < 1) {
            throw new HttpException(422, 'Page numbers start at 1.');
        }

        if ($pageSize !== null && ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE)) {
            throw new HttpException(422, sprintf('Ask for between 1 and %d rows.', self::MAX_PAGE_SIZE));
        }

        $order = trim($request->string('order')->value());

        return new self(
            search: mb_substr(trim($request->string('search')->value()), 0, 100),
            page: $page,
            pageSize: $pageSize,
            order: $order === '' ? null : mb_substr($order, 0, 64),
        );
    }

    public function isPaginated(): bool
    {
        return $this->page !== null || $this->pageSize !== null;
    }

    public function pageNumber(): int
    {
        return $this->page ?? 1;
    }

    public function size(): int
    {
        return $this->pageSize ?? self::DEFAULT_PAGE_SIZE;
    }

    public function offset(): int
    {
        return ($this->pageNumber() - 1) * $this->size();
    }

    public function searchTerm(): ?string
    {
        $term = trim($this->search);

        return $term === '' ? null : mb_strtolower($term);
    }

    private static function readInt(Request $request, string $key): ?int
    {
        if (! $request->has($key) || $request->string($key)->value() === '') {
            return null;
        }

        $value = $request->string($key)->value();

        if (! ctype_digit(ltrim($value, '-'))) {
            throw new HttpException(422, sprintf('"%s" has to be a whole number.', $key));
        }

        return (int) $value;
    }
}
