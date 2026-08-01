<?php

namespace App\DTO\Order;

class CheckoutRequest
{
    /**
     * Use an existing saved address by its ID.
     * If null, street/city/country must be provided.
     */
    public ?int $addressId = null;

    public ?string $street = null;

    public ?string $city = null;

    public ?string $postalCode = null;

    public ?string $country = null;

    public ?string $contactPhone = null;
}
