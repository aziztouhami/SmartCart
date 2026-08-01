<?php

namespace App\DTO\Address;

class CreateAddressRequest
{
    public string $label = '';

    public string $street = '';

    public string $city = '';

    public ?string $postalCode = null;

    public string $country = '';

    public bool $isDefault = false;

    public ?float $lat = null;

    public ?float $lng = null;
}
