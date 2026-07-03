<?php

namespace App\DTO\Order;

use Symfony\Component\Validator\Constraints as Assert;

class CheckoutRequest
{
    /**
     * Use an existing saved address by its ID.
     * If null, street/city/country must be provided.
     */
    public ?int $addressId = null;

    #[Assert\Length(max: 255)]
    public ?string $street = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 20)]
    public ?string $postalCode = null;

    #[Assert\Length(max: 100)]
    public ?string $country = null;

    #[Assert\NotBlank(message: 'Phone number is required to place an order.')]
    #[Assert\Length(max: 30)]
    public ?string $contactPhone = null;
}
