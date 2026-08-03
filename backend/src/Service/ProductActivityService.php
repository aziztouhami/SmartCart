<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\GuestEventRepository;
use App\Repository\InteractionRepository;
use App\Repository\OrderRepository;

/**
 * Live (request-time, not precomputed) social-proof numbers for a product
 * page: how many people are looking at it right now, and how many active
 * carts it's sitting in. Deliberately a raw count of real tracked
 * activity — no synthetic floor or randomization — so it under-reports on a
 * quiet catalog rather than show a fabricated number.
 */
class ProductActivityService
{
    // "Right now" is approximated as a short recent window, since there's no
    // live presence/heartbeat tracking — a page view in the last few minutes
    // is treated as still browsing.
    private const VIEWING_WINDOW_MINUTES = 10;

    public function __construct(
        private InteractionRepository $interactionRepository,
        private GuestEventRepository $guestEventRepository,
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * @return array{viewingNow: int, inCarts: int}
     */
    public function getActivity(Product $product): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d minutes', self::VIEWING_WINDOW_MINUTES));

        $viewingNow = $this->interactionRepository->countDistinctUsersViewingSince($product, $since)
            + $this->guestEventRepository->countDistinctSessionsViewingSince($product, $since);

        return [
            'viewingNow' => $viewingNow,
            'inCarts' => $this->orderRepository->countActiveCartsContainingProduct($product),
        ];
    }
}
