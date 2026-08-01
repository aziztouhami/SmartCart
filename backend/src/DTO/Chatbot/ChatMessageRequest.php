<?php

namespace App\DTO\Chatbot;

class ChatMessageRequest
{
    public string $message = '';

    /**
     * Recent turns the client is already holding in its own widget state,
     * sent back so the model has short-term context. The backend stays
     * stateless beyond the rate-limit log — it doesn't need to persist or
     * replay history itself.
     *
     * @var array<int, array{role: string, content: string}>
     */
    public array $history = [];
}
