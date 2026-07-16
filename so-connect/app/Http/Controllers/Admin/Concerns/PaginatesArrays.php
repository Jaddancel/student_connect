<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait PaginatesArrays
{
    /**
     * Wrap an in-memory array/collection of rows in a LengthAwarePaginator that
     * preserves the current query string and supports an independent page
     * parameter (so several tables can paginate on the same page).
     *
     * @param  array<int, mixed>|Collection<int, mixed>  $items
     */
    protected function paginateArray(
        array|Collection $items,
        int $perPage = 15,
        string $pageName = 'page'
    ): LengthAwarePaginator {
        $items = $items instanceof Collection ? $items : collect($items);
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        ))->withQueryString();
    }
}
