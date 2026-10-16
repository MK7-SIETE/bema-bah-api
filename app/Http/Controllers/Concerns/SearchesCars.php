<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Car;
use App\Support\CarSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Car searching and paging, written ONCE and shared by every controller that lists cars
 * (CarController and RentalCarController). Fix a bug here and it is fixed everywhere.
 */
trait SearchesCars
{
    /**
     * Shared search + paging.
     *
     *  - no search text      -> newest first, paged by MySQL
     *  - $staff and the text looks like a vehicle ID (BB-0012) -> that exact car
     *  - otherwise           -> typo-tolerant search, best match first, paged
     *
     * Always returns the same shape: { data: [...], current_page, last_page, per_page, total, ... }
     * Customers use "load more": ask for ?page=2 while current_page < last_page.
     */
    protected function searchAndPaginate(
        Builder $query,
        Request $request,
        int $perPage,
        array $with = [],
        bool $staff = false
    ): LengthAwarePaginator {
        $term = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));

        if ($term === '') {
            return $query->with($with)->latest()->paginate($perPage)->withQueryString();
        }

        if ($staff && ($carId = CarSearch::vehicleId($term)) !== null) {
            return $query->with($with)->where('id', $carId)->paginate($perPage)->withQueryString();
        }

        $ids     = CarSearch::rankedIds($query, $term);
        $pageIds = array_slice($ids, ($page - 1) * $perPage, $perPage);

        $cars = Car::with($with)
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn ($car) => array_search($car->id, $pageIds))
            ->values();

        return new LengthAwarePaginator($cars, count($ids), $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * Filters used by the staff lists (dealer, rental admin, super admin):
     *   ?status=live|hidden   ?type=sale|rental   ?dealer=<user id>  (super admin only)
     */
    protected function applyStaffFilters(Builder $query, Request $request, bool $allowDealer = false): void
    {
        $status = $request->input('status');
        if ($status === 'live') {
            $query->where('is_available', true);
        } elseif ($status === 'hidden') {
            $query->where('is_available', false);
        }

        $type = $request->input('type');
        if (in_array($type, ['sale', 'rental'], true)) {
            $query->where('listing_type', $type);
        }

        if ($allowDealer && $request->filled('dealer')) {
            $query->where('user_id', (int) $request->input('dealer'));
        }
    }
}
