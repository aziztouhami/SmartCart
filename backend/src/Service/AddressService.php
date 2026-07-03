<?php

namespace App\Service;

use App\DTO\Address\CreateAddressRequest;
use App\DTO\Address\UpdateAddressRequest;
use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;

class AddressService
{
    public function __construct(
        private AddressRepository $addressRepository,
        private EntityManagerInterface $em,
    ) {}

    public function create(User $user, CreateAddressRequest $dto): Address
    {
        if ($dto->isDefault) {
            $this->addressRepository->clearDefaultForUser($user);
        }

        $address = new Address();
        $address->setUser($user);
        $address->setLabel($dto->label);
        $address->setStreet($dto->street);
        $address->setCity($dto->city);
        $address->setPostalCode($dto->postalCode);
        $address->setCountry($dto->country);
        $address->setIsDefault($dto->isDefault);
        $address->setLatitude($dto->lat);
        $address->setLongitude($dto->lng);

        $this->em->persist($address);
        $this->em->flush();

        return $address;
    }

    public function update(Address $address, UpdateAddressRequest $dto, User $user): Address
    {
        if ($dto->label !== null)      $address->setLabel($dto->label);
        if ($dto->street !== null)     $address->setStreet($dto->street);
        if ($dto->city !== null)       $address->setCity($dto->city);
        if ($dto->postalCode !== null) $address->setPostalCode($dto->postalCode);
        if ($dto->country !== null)    $address->setCountry($dto->country);
        if ($dto->isDefault === true) {
            $this->addressRepository->clearDefaultForUser($user);
            $address->setIsDefault(true);
        } elseif ($dto->isDefault === false) {
            $address->setIsDefault(false);
        }
        if ($dto->lat !== null) $address->setLatitude($dto->lat);
        if ($dto->lng !== null) $address->setLongitude($dto->lng);

        $this->em->flush();

        return $address;
    }

    public function delete(Address $address): void
    {
        $this->em->remove($address);
        $this->em->flush();
    }
}
