<?php

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ImageControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setIsVerified(true);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password123'));
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]));
        $this->adminToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    private function imageFile(): UploadedFile
    {
        // UploadedFile::move() in test mode renames (not copies) the source
        // file, so each upload needs its own throwaway copy of the fixture.
        $copy = tempnam(sys_get_temp_dir(), 'upload').'.png';
        copy(__DIR__.'/../../Fixtures/test-image.png', $copy);

        return new UploadedFile($copy, 'test-image.png', 'image/png', null, true);
    }

    public function testUploadRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/upload', [], ['file' => $this->imageFile()]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadForbiddenForNonAdmin(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $plain = new User();
        $plain->setEmail('plain@example.com');
        $plain->setFirstName('Plain');
        $plain->setLastName('User');
        $plain->setIsVerified(true);
        $plain->setPassword($hasher->hashPassword($plain, 'password123'));
        $this->em->persist($plain);
        $this->em->flush();

        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'plain@example.com',
            'password' => 'password123',
        ]));
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/upload', [], ['file' => $this->imageFile()], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUploadFailsWithoutFile(): void
    {
        $this->client->request('POST', '/api/upload', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAdminCanUploadImage(): void
    {
        $this->client->request('POST', '/api/upload', [], ['file' => $this->imageFile()], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('url', $data);
        $this->assertStringContainsString('/uploads/', $data['url']);

        $path = parse_url($data['url'], PHP_URL_PATH);
        $diskPath = static::getContainer()->getParameter('kernel.project_dir').'/public'.$path;
        $this->assertFileExists($diskPath);
        unlink($diskPath);
    }

    public function testUploadRejectsUnsupportedMimeType(): void
    {
        $textFile = tempnam(sys_get_temp_dir(), 'upload').'.txt';
        file_put_contents($textFile, 'not an image');
        $uploaded = new UploadedFile($textFile, 'note.txt', 'text/plain', null, true);

        $this->client->request('POST', '/api/upload', [], ['file' => $uploaded], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->adminToken]);

        $this->assertResponseStatusCodeSame(400);
        unlink($textFile);
    }
}
