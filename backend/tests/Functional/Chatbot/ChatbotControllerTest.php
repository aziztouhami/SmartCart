<?php

namespace App\Tests\Functional\Chatbot;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ChatbotControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\ChatMessageLog')->execute();
    }

    public function testMessageRequiresSessionIdHeader(): void
    {
        $this->client->request('POST', '/api/chatbot/message', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'message' => 'Hello',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMessageRejectsBlankMessage(): void
    {
        $this->client->request('POST', '/api/chatbot/message', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SESSION_ID' => 'session-1'], content: json_encode([
            'message' => '',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMessageReturnsAReply(): void
    {
        // No GROQ_API_KEY in the test environment — ChatbotService falls back
        // to its canned reply rather than failing, so this stays deterministic.
        $this->client->request('POST', '/api/chatbot/message', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SESSION_ID' => 'session-2'], content: json_encode([
            'message' => 'Hello, do you sell phones?',
        ]));

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['reply']);
    }
}
