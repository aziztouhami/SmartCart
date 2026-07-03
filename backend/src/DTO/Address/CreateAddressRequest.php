<?php

namespace App\DTO\Address;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAddressRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $label = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $street = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $city = '';

    #[Assert\Length(max: 20)]
    public ?string $postalCode = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $country = '';

    public bool $isDefault = false;

    public ?float $lat = null;

    public ?float $lng = null;
}
