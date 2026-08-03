<?php

namespace App\Service\Chatbot;

use App\Entity\ChatMessageLog;
use App\Repository\ChatMessageLogRepository;
use App\Service\Ai\GroqClientService;

/**
 * Orchestrates one chat turn: logs the message (also the rate-limit
 * counter), finds grounding products (ChatProductFinder), builds the full
 * prompt (ChatPromptDataBuilder), calls the LLM (Llama 3.3 70B via Groq),
 * logs and returns the reply.
 */
class ChatbotService
{
    private const RATE_LIMIT_WINDOW_SECONDS = 60;
    private const RATE_LIMIT_MAX_MESSAGES = 12;
    private const FALLBACK_REPLY = "Désolé, je n'arrive pas à répondre pour le moment. Merci de réessayer dans un instant.";

    public function __construct(
        private GroqClientService $aiClient,
        private ChatProductFinder $productFinder,
        private ChatPromptDataBuilder $promptDataBuilder,
        private ChatMessageLogRepository $chatMessageLogRepository,
    ) {
    }

    public function isRateLimited(string $sessionId): bool
    {
        $since = new \DateTimeImmutable('-'.self::RATE_LIMIT_WINDOW_SECONDS.' seconds');

        return $this->chatMessageLogRepository->countUserMessagesSince($sessionId, $since) >= self::RATE_LIMIT_MAX_MESSAGES;
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function reply(string $sessionId, string $message, array $history): string
    {
        $this->log($sessionId, 'user', $message);

        $products = $this->productFinder->find($message, $history);
        $prompt = $this->promptDataBuilder->build($message, $products, $history);

        $reply = $this->aiClient->generate($prompt) ?? self::FALLBACK_REPLY;

        $this->log($sessionId, 'assistant', $reply);

        return $reply;
    }

    private function log(string $sessionId, string $role, string $message): void
    {
        $entry = (new ChatMessageLog())
            ->setSessionId($sessionId)
            ->setRole($role)
            ->setMessage($message);
        $this->chatMessageLogRepository->save($entry, true);
    }
}
