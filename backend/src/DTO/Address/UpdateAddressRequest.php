<?php

namespace App\DTO\Address;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAddressRequest
{
    #[Assert\Length(max: 100)]
    public ?string $label = null;

    #[Assert\Length(max: 255)]
    public ?string $street = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 20)]
    public ?string $postalCode = null;

    #[Assert\Length(max: 100)]
    public ?string $country = null;

    public ?bool $isDefault = null;

    public ?float $lat = null;

    public ?float $lng = null;
}
