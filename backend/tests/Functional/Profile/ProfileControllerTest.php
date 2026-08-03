<?php

namespace App\Tests\Functional\Profile;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function tokenFor(string $email, string $password = 'password123'): string
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function headers(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'CONTENT_TYPE' => 'application/json'];
    }

    public function testGetRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/profile');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetReturnsOwnProfile(): void
    {
        $token = $this->tokenFor('profile1@example.com');

        $this->client->request('GET', '/api/profile', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('profile1@example.com', $data['email']);
    }

    public function testUpdateChangesProfileFields(): void
    {
        $token = $this->tokenFor('profile2@example.com');

        $this->client->request('PUT', '/api/profile', server: $this->headers($token), content: json_encode([
            'firstName' => 'Updated',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Updated', $data['firstName']);
    }

    public function testChangePasswordSucceedsWithCorrectCurrentPassword(): void
    {
        $token = $this->tokenFor('profile3@example.com', 'oldPassword1');

        $this->client->request('PUT', '/api/profile/password', server: $this->headers($token), content: json_encode([
            'currentPassword' => 'oldPassword1',
            'newPassword' => 'newPassword1',
        ]));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testChangePasswordFailsWithWrongCurrentPassword(): void
    {
        $token = $this->tokenFor('profile4@example.com', 'oldPassword1');

        $this->client->request('PUT', '/api/profile/password', server: $this->headers($token), content: json_encode([
            'currentPassword' => 'wrong-password',
            'newPassword' => 'newPassword1',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteSchedulesAccountDeletion(): void
    {
        $token = $this->tokenFor('profile5@example.com');

        $this->client->request('DELETE', '/api/profile', server: $this->headers($token));

        $this->assertResponseStatusCodeSame(200);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'profile5@example.com']);
        $this->assertNotNull($user->getDeletionRequestedAt());
    }
}
