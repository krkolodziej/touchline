<?php

declare(strict_types=1);

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Turns a query plus a ListQuery into what a collection renders.
 *
 * Written once and used by every list in the application, so that "how does search work" and
 * "what does page two look like" have one answer rather than one per controller.
 */
class Listing
{
    /**
     * Applies an allow-listed sort.
     *
     * The allow-list maps a *wire* name onto a column, which is the point: a caller never
     * names a column, so `order` cannot be used to sort by something the resource does not
     * expose, and renaming a column does not change the contract.
     *
     * An unknown field is refused, and the message names what is allowed. Silently ignoring
     * it is how a list ends up in the wrong order in production while every response still
     * looks perfectly plausible.
     *
     * @param  Builder<covariant Model>  $builder
     * @param  array<string, string>  $allowed  wire name => column
     */
    public function sort(Builder $builder, ListQuery $query, array $allowed, string $default): void
    {
        $requested = $query->order ?? $default;
        $descending = str_starts_with($requested, '-');
        $field = ltrim($requested, '-');

        if (! isset($allowed[$field])) {
            throw new HttpException(400, sprintf(
                'Cannot order by "%s". Try one of: %s.',
                $field,
                implode(', ', array_keys($allowed)),
            ));
        }

        $builder->orderBy($allowed[$field], $descending ? 'desc' : 'asc');

        // A tiebreaker on the primary key, always. Without one, two rows with the same name
        // can swap places between requests, and somebody paging through the list sees one of
        // them twice and never sees the other.
        $builder->orderBy($builder->getModel()->getTable().'.id');
    }

    /**
     * Fetches the page and hands it to the mapper — all of it, not one row at a time.
     *
     * A page-level mapper rather than a per-row one, because that is the difference between a
     * list costing one query and a list costing one query per row. Most resources carry
     * something the row cannot answer for on its own — a player's current club, a club's
     * squad size, a league's latest season — and fetching that inside a per-row mapper is the
     * textbook N+1 wearing a closure. Given the whole page, a mapper asks for every row's
     * aggregate at once and then hands each row its own.
     *
     * @template TModel of Model
     * @template TResource
     *
     * @param  Builder<TModel>  $builder
     * @param  callable(list<TModel>): list<TResource>  $mapPage
     * @return list<TResource>|array<string, mixed>
     */
    public function respond(Builder $builder, ListQuery $query, callable $mapPage): array
    {
        if (! $query->isPaginated()) {
            /** @var list<TModel> $rows */
            $rows = $builder->get()->all();

            return $mapPage($rows);
        }

        $count = $builder->toBase()->getCountForPagination();
        $page = $query->pageNumber();
        $lastPage = max(1, (int) ceil($count / $query->size()));

        /** @var list<TModel> $rows */
        $rows = $builder->offset($query->offset())->limit($query->size())->get()->all();

        return [
            'count' => $count,
            'page' => $page,
            'page_size' => $query->size(),
            // Page numbers rather than absolute URLs. A URL bakes the public host name into
            // every response, which then has to be right behind a proxy, in tests and in a
            // container; anybody who already knows the address can append `?page=`.
            'next' => $page < $lastPage ? $page + 1 : null,
            'previous' => $page > 1 ? $page - 1 : null,
            'results' => $mapPage($rows),
        ];
    }
}
