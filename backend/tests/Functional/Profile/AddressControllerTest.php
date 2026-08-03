<?php

namespace App\Tests\Functional\Profile;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AddressControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Address')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function tokenFor(string $email): array
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        // Return the id, not the entity — the login request above reboots the
        // kernel, so any entity reference obtained before it is stale against
        // the fresh EntityManager the reboot creates. Callers re-fetch by id.
        return [$token, $user->getId()];
    }

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/profile/addresses');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateAddress(): void
    {
        [$token] = $this->tokenFor('addr1@example.com');

        $this->client->request('POST', '/api/profile/addresses', server: $this->headers($token), content: json_encode([
            'label' => 'Home',
            'street' => '1 Main St',
            'city' => 'Casablanca',
            'country' => 'Morocco',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Home', $data['label']);
    }

    public function testCreateFailsValidationForMissingStreet(): void
    {
        [$token] = $this->tokenFor('addr2@example.com');

        $this->client->request('POST', '/api/profile/addresses', server: $this->headers($token), content: json_encode([
            'label' => 'Home',
            'city' => 'Casablanca',
            'country' => 'Morocco',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testListReturnsOnlyOwnAddresses(): void
    {
        [$token, $userId] = $this->tokenFor('addr3@example.com');
        $user = $this->em->getRepository(User::class)->find($userId);

        $address = new Address();
        $address->setLabel('Home');
        $address->setStreet('1 Main St');
        $address->setCity('Casablanca');
        $address->setCountry('Morocco');
        $address->setUser($user);
        $this->em->persist($address);
        $this->em->flush();

        $this->client->request('GET', '/api/profile/addresses', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Home', $data[0]['label']);
    }

    public function testUpdateReturns404ForAnotherUsersAddress(): void
    {
        [, $ownerId] = $this->tokenFor('addr-owner@example.com');
        [$otherToken] = $this->tokenFor('addr-other@example.com');
        $owner = $this->em->getRepository(User::class)->find($ownerId);

        $address = new Address();
        $address->setLabel('Home');
        $address->setStreet('1 Main St');
        $address->setCity('Casablanca');
        $address->setCountry('Morocco');
        $address->setUser($owner);
        $this->em->persist($address);
        $this->em->flush();

        $this->client->request('PUT', '/api/profile/addresses/'.$address->getId(), server: $this->headers($otherToken), content: json_encode([
            'label' => 'Hacked',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteOwnAddress(): void
    {
        [$token, $userId] = $this->tokenFor('addr4@example.com');
        $user = $this->em->getRepository(User::class)->find($userId);

        $address = new Address();
        $address->setLabel('Home');
        $address->setStreet('1 Main St');
        $address->setCity('Casablanca');
        $address->setCountry('Morocco');
        $address->setUser($user);
        $this->em->persist($address);
        $this->em->flush();
        $id = $address->getId();

        $this->client->request('DELETE', '/api/profile/addresses/'.$id, server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->em->getRepository(Address::class)->find($id));
    }
}
