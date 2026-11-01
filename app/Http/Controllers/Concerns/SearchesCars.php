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
     * Shared search + paging + sorting.
     *
     *  - no search text      -> the chosen sort (newest first by default), paged by MySQL
     *  - $staff and the text looks like a vehicle ID (BB-0012) -> that exact car
     *  - otherwise           -> typo-tolerant search, best match first, paged.
     *                           If the visitor picked a sort other than "newest", that sort wins
     *                           among the cars that matched.
     *
     * ?sort=newest | price_asc | price_desc | mileage | year      (anything else means newest)
     *
     * Always returns the same shape: { data: [...], current_page, last_page, per_page, total, ... }
     * Customers use "load more": ask for ?page=2 while current_page < last_page.
     *
     * $priceColumn is the column "price_asc" and "price_desc" sort by: 'price' for cars for sale,
     * 'rental_price_per_day' for rentals.
     */
    protected function searchAndPaginate(
        Builder $query,
        Request $request,
        int $perPage,
        array $with = [],
        bool $staff = false,
        string $priceColumn = 'price'
    ): LengthAwarePaginator {
        $term  = trim((string) $request->input('search', ''));
        $page  = max(1, (int) $request->input('page', 1));
        $order = $this->sortOrder((string) $request->input('sort', 'newest'), $priceColumn);

        if ($term === '') {
            $listing = $query->with($with);
            if ($order) {
                // The second sort (newest id first) keeps cars with the same price / year / mileage in a steady order between pages.
                $listing->orderBy($order[0], $order[1])->orderByDesc('id');
            } else {
                $listing->latest()->orderByDesc('id');
            }
            return $listing->paginate($perPage)->withQueryString();
        }

        if ($staff && ($carId = CarSearch::vehicleId($term)) !== null) {
            return $query->with($with)->where('id', $carId)->paginate($perPage)->withQueryString();
        }

        $ids = CarSearch::rankedIds($query, $term);

        // An explicit sort wins over "best match first", but only among the cars that matched.
        if ($order && $ids) {
            $ids = Car::whereIn('id', $ids)->orderBy($order[0], $order[1])->orderByDesc('id')->pluck('id')->all();
        }

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
     * The sorts the Inventory page offers: [column, direction].
     * Only these fixed columns can ever be used, whatever the address says.
     * null = "newest first" (the default, also used for any unknown value).
     */
    protected function sortOrder(string $sort, string $priceColumn = 'price'): ?array
    {
        return match ($sort) {
            'price_asc'  => [$priceColumn, 'asc'],
            'price_desc' => [$priceColumn, 'desc'],
            'mileage'    => ['mileage', 'asc'],
            'year'       => ['year', 'desc'],
            default      => null,
        };
    }

    /**
     * Filters used by the staff lists (dealer, rental admin, super admin):
     *   ?status=live|hidden   ?type=sale|rental   ?dealer=<user id>  (super admin only)
     */
    protected function applyStaffFilters(Builder $query, Request $request, bool $allowDealer = false): void
    {
        $status = $request->input('status');
        $isSuper = (bool) $request->user()?->isSuper();

        if ($isSuper) {
            // Super admin list:  live = on the site (not sold, not hidden)
            //                    hidden = hidden by the admin    sold = marked sold by the dealer
            if ($status === 'live') {
                $query->where('is_available', true)->where('hidden_by_admin', false);
            } elseif ($status === 'hidden') {
                $query->where('hidden_by_admin', true);
            } elseif ($status === 'sold') {
                $query->where('is_available', false);
            }
        } else {
            // Dealer / rental admin list:  live = available,  hidden = sold
            if ($status === 'live') {
                $query->where('is_available', true);
            } elseif ($status === 'hidden') {
                $query->where('is_available', false);
            }
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
