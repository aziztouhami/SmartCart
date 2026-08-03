<?php

namespace App\Tests\Unit\Service;

use App\DTO\Address\CreateAddressRequest;
use App\DTO\Address\UpdateAddressRequest;
use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Service\AddressService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AddressServiceTest extends TestCase
{
    private AddressRepository $addressRepository;
    private EntityManagerInterface $em;
    private AddressService $service;

    protected function setUp(): void
    {
        $this->addressRepository = $this->createMock(AddressRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new AddressService($this->addressRepository, $this->em);
    }

    public function testCreateBuildsAddressFromDto(): void
    {
        $user = new User();
        $dto = new CreateAddressRequest();
        $dto->label = 'Home';
        $dto->street = '1 Main St';
        $dto->city = 'Casablanca';
        $dto->postalCode = '20000';
        $dto->country = 'Morocco';
        $dto->lat = 33.5;
        $dto->lng = -7.6;

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Address::class));
        $this->em->expects($this->once())->method('flush');

        $address = $this->service->create($user, $dto);

        $this->assertSame('Home', $address->getLabel());
        $this->assertSame('1 Main St', $address->getStreet());
        $this->assertSame($user, $address->getUser());
        $this->assertSame(33.5, $address->getLatitude());
    }

    public function testCreateClearsPreviousDefaultWhenNewAddressIsDefault(): void
    {
        $user = new User();
        $dto = new CreateAddressRequest();
        $dto->label = 'Home';
        $dto->street = '1 Main St';
        $dto->city = 'Casablanca';
        $dto->country = 'Morocco';
        $dto->isDefault = true;

        $this->addressRepository->expects($this->once())->method('clearDefaultForUser')->with($user);

        $address = $this->service->create($user, $dto);

        $this->assertTrue($address->isDefault());
    }

    public function testCreateDoesNotClearDefaultWhenNotDefault(): void
    {
        $user = new User();
        $dto = new CreateAddressRequest();
        $dto->label = 'Home';
        $dto->street = '1 Main St';
        $dto->city = 'Casablanca';
        $dto->country = 'Morocco';

        $this->addressRepository->expects($this->never())->method('clearDefaultForUser');

        $this->service->create($user, $dto);
    }

    public function testUpdateOnlyChangesSuppliedFields(): void
    {
        $user = new User();
        $address = new Address();
        $address->setLabel('Old Label');
        $address->setStreet('Old Street');
        $address->setCity('Old City');
        $address->setCountry('Old Country');

        $dto = new UpdateAddressRequest();
        $dto->label = 'New Label';

        $this->service->update($address, $dto, $user);

        $this->assertSame('New Label', $address->getLabel());
        $this->assertSame('Old Street', $address->getStreet());
    }

    public function testUpdateSettingIsDefaultTrueClearsOthers(): void
    {
        $user = new User();
        $address = new Address();
        $dto = new UpdateAddressRequest();
        $dto->isDefault = true;

        $this->addressRepository->expects($this->once())->method('clearDefaultForUser')->with($user);

        $this->service->update($address, $dto, $user);

        $this->assertTrue($address->isDefault());
    }

    public function testUpdateSettingIsDefaultFalseDoesNotClearOthers(): void
    {
        $user = new User();
        $address = new Address();
        $address->setIsDefault(true);
        $dto = new UpdateAddressRequest();
        $dto->isDefault = false;

        $this->addressRepository->expects($this->never())->method('clearDefaultForUser');

        $this->service->update($address, $dto, $user);

        $this->assertFalse($address->isDefault());
    }

    public function testUpdateLeavesIsDefaultUnchangedWhenNotProvided(): void
    {
        $user = new User();
        $address = new Address();
        $address->setIsDefault(true);
        $dto = new UpdateAddressRequest();

        $this->addressRepository->expects($this->never())->method('clearDefaultForUser');

        $this->service->update($address, $dto, $user);

        $this->assertTrue($address->isDefault());
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $address = new Address();

        $this->em->expects($this->once())->method('remove')->with($address);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($address);
    }
}
