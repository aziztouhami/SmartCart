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
    ) {
    }

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
        if (null !== $dto->label) {
            $address->setLabel($dto->label);
        }
        if (null !== $dto->street) {
            $address->setStreet($dto->street);
        }
        if (null !== $dto->city) {
            $address->setCity($dto->city);
        }
        if (null !== $dto->postalCode) {
            $address->setPostalCode($dto->postalCode);
        }
        if (null !== $dto->country) {
            $address->setCountry($dto->country);
        }
        if (true === $dto->isDefault) {
            $this->addressRepository->clearDefaultForUser($user);
            $address->setIsDefault(true);
        } elseif (false === $dto->isDefault) {
            $address->setIsDefault(false);
        }
        if (null !== $dto->lat) {
            $address->setLatitude($dto->lat);
        }
        if (null !== $dto->lng) {
            $address->setLongitude($dto->lng);
        }

        $this->em->flush();

        return $address;
    }

    public function delete(Address $address): void
    {
        $this->em->remove($address);
        $this->em->flush();
    }
}
