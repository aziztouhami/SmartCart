<?php

namespace App\Tests\Functional\Documentation;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DocumentationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSpecReturnsValidOpenApiJson(): void
    {
        $this->client->request('GET', '/api/docs.json');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertNotEmpty($data['paths']);
    }

    public function testUiReturnsSwaggerHtmlPage(): void
    {
        $this->client->request('GET', '/api/docs');

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('text/html', $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('swagger-ui', $this->client->getResponse()->getContent());
    }
}
