<?php

namespace App\DTO\Address;

class UpdateAddressRequest
{
    public ?string $label = null;

    public ?string $street = null;

    public ?string $city = null;

    public ?string $postalCode = null;

    public ?string $country = null;

    public ?bool $isDefault = null;

    public ?float $lat = null;

    public ?float $lng = null;
}
