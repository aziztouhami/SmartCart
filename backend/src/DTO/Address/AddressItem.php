<?php

namespace App\DTO\Address;

use App\Entity\Address;

class AddressItem
{
    public int $id;
    public string $label;
    public string $street;
    public string $city;
    public ?string $postalCode;
    public string $country;
    public bool $isDefault;
    public ?float $lat;
    public ?float $lng;
    public string $createdAt;

    public static function fromEntity(Address $address): self
    {
        $dto = new self();
        $dto->id = $address->getId();
        $dto->label = $address->getLabel();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity();
        $dto->postalCode = $address->getPostalCode();
        $dto->country = $address->getCountry();
        $dto->isDefault = $address->isDefault();
        $dto->lat = $address->getLatitude();
        $dto->lng = $address->getLongitude();
        $dto->createdAt = $address->getCreatedAt()->format(\DateTimeInterface::ATOM);
        return $dto;
    }
}
