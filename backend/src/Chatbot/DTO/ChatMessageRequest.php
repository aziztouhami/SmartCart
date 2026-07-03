<?php

namespace App\Chatbot\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChatMessageRequest
{
    #[Assert\NotBlank(message: 'Message is required')]
    #[Assert\Length(max: 1000, maxMessage: 'Message must be at most {{ limit }} characters')]
    public string $message = '';

    /**
     * Recent turns the client is already holding in its own widget state,
     * sent back so Gemini has short-term context. The backend stays
     * stateless beyond the rate-limit log — it doesn't need to persist or
     * replay history itself.
     *
     * @var array<int, array{role: string, content: string}>
     */
    public array $history = [];
}
