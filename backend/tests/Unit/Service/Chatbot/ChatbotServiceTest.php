<?php

namespace App\Tests\Unit\Service\Chatbot;

use App\Entity\ChatMessageLog;
use App\Repository\ChatMessageLogRepository;
use App\Service\Ai\GroqClientService;
use App\Service\Chatbot\ChatbotService;
use App\Service\Chatbot\ChatProductFinder;
use App\Service\Chatbot\ChatPromptDataBuilder;
use PHPUnit\Framework\TestCase;

class ChatbotServiceTest extends TestCase
{
    private const FALLBACK_REPLY = "Désolé, je n'arrive pas à répondre pour le moment. Merci de réessayer dans un instant.";

    private GroqClientService $aiClient;
    private ChatProductFinder $productFinder;
    private ChatPromptDataBuilder $promptDataBuilder;
    private ChatMessageLogRepository $chatMessageLogRepository;
    private ChatbotService $service;

    protected function setUp(): void
    {
        $this->aiClient = $this->createMock(GroqClientService::class);
        $this->productFinder = $this->createMock(ChatProductFinder::class);
        $this->promptDataBuilder = $this->createMock(ChatPromptDataBuilder::class);
        $this->chatMessageLogRepository = $this->createMock(ChatMessageLogRepository::class);

        $this->service = new ChatbotService(
            $this->aiClient,
            $this->productFinder,
            $this->promptDataBuilder,
            $this->chatMessageLogRepository,
        );
    }

    public function testReplyReturnsAiGeneratedText(): void
    {
        $this->productFinder->method('find')->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn('Bonjour, comment puis-je vous aider ?');

        $reply = $this->service->reply('session-1', 'Salut', []);

        $this->assertSame('Bonjour, comment puis-je vous aider ?', $reply);
    }

    public function testReplyFallsBackToCannedReplyWhenAiClientReturnsNull(): void
    {
        $this->productFinder->method('find')->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn(null);

        $reply = $this->service->reply('session-1', 'Salut', []);

        $this->assertSame(self::FALLBACK_REPLY, $reply);
    }

    public function testReplyLogsUserMessageThenAssistantReply(): void
    {
        $this->productFinder->method('find')->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn('AI reply');

        $logged = [];
        $this->chatMessageLogRepository->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function (ChatMessageLog $entry) use (&$logged) {
                $logged[] = [$entry->getSessionId(), $entry->getRole(), $entry->getMessage()];

                return true;
            }), true);

        $this->service->reply('session-42', 'Bonjour', []);

        $this->assertSame([
            ['session-42', 'user', 'Bonjour'],
            ['session-42', 'assistant', 'AI reply'],
        ], $logged);
    }

    public function testReplyLogsFallbackReplyWhenAiClientReturnsNull(): void
    {
        $this->productFinder->method('find')->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn(null);

        $logged = [];
        $this->chatMessageLogRepository->method('save')
            ->willReturnCallback(function (ChatMessageLog $entry) use (&$logged) {
                $logged[] = [$entry->getRole(), $entry->getMessage()];
            });

        $this->service->reply('session-1', 'Salut', []);

        $this->assertSame([
            ['user', 'Salut'],
            ['assistant', self::FALLBACK_REPLY],
        ], $logged);
    }

    public function testReplyPassesMessageAndHistoryToProductFinder(): void
    {
        $history = [['role' => 'user', 'content' => 'previous turn']];

        $this->productFinder->expects($this->once())
            ->method('find')
            ->with('current message', $history)
            ->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn('reply');

        $this->service->reply('session-1', 'current message', $history);
    }

    public function testReplyPassesFoundProductsAndHistoryToPromptDataBuilder(): void
    {
        $history = [['role' => 'assistant', 'content' => 'earlier reply']];
        $products = ['fake-product-marker'];

        $this->productFinder->method('find')->willReturn($products);
        $this->promptDataBuilder->expects($this->once())
            ->method('build')
            ->with('current message', $products, $history)
            ->willReturn('PROMPT');
        $this->aiClient->method('generate')->willReturn('reply');

        $this->service->reply('session-1', 'current message', $history);
    }

    public function testReplyPassesBuiltPromptToAiClient(): void
    {
        $this->productFinder->method('find')->willReturn([]);
        $this->promptDataBuilder->method('build')->willReturn('THE FULL PROMPT');

        $this->aiClient->expects($this->once())
            ->method('generate')
            ->with('THE FULL PROMPT')
            ->willReturn('reply');

        $this->service->reply('session-1', 'hello', []);
    }

    public function testIsRateLimitedReturnsFalseWhenUnderLimit(): void
    {
        $this->chatMessageLogRepository->method('countUserMessagesSince')->willReturn(11);

        $this->assertFalse($this->service->isRateLimited('session-1'));
    }

    public function testIsRateLimitedReturnsTrueAtExactLimit(): void
    {
        $this->chatMessageLogRepository->method('countUserMessagesSince')->willReturn(12);

        $this->assertTrue($this->service->isRateLimited('session-1'));
    }

    public function testIsRateLimitedReturnsTrueWhenOverLimit(): void
    {
        $this->chatMessageLogRepository->method('countUserMessagesSince')->willReturn(13);

        $this->assertTrue($this->service->isRateLimited('session-1'));
    }

    public function testIsRateLimitedQueriesWithGivenSessionIdAndSixtySecondWindow(): void
    {
        $this->chatMessageLogRepository->expects($this->once())
            ->method('countUserMessagesSince')
            ->with(
                'session-99',
                $this->callback(function (\DateTimeImmutable $since) {
                    $now = new \DateTimeImmutable();
                    $diff = $now->getTimestamp() - $since->getTimestamp();

                    // Should be ~60 seconds in the past; allow a little slack for test execution time.
                    return $diff >= 59 && $diff <= 65;
                })
            )
            ->willReturn(0);

        $this->service->isRateLimited('session-99');
    }
}
