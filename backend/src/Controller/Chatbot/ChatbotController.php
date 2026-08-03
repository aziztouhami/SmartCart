<?php

namespace App\Controller\Chatbot;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Chatbot\ChatMessageRequest;
use App\Service\Chatbot\ChatbotService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chatbot', name: 'api_chatbot_')]
#[OA\Tag(name: 'Chatbot', description: 'Shop assistant chatbot, grounded on the product catalogue (Llama 3.3 70B via Groq)')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private ChatbotService $chatbotService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    #[Route('/message', name: 'message', methods: ['POST'])]
    #[OA\Post(
        path: '/api/chatbot/message',
        operationId: 'sendChatbotMessage',
        summary: 'Send a message to the shop assistant',
        description: 'Stateless beyond a per-session rate limit — pass recent turns back in "history" for short-term context.',
        parameters: [
            new OA\Parameter(name: 'X-Session-Id', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', maxLength: 1000),
                    new OA\Property(
                        property: 'history',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'role', type: 'string', enum: ['user', 'assistant']),
                                new OA\Property(property: 'content', type: 'string'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Assistant reply'),
            new OA\Response(response: 400, description: 'Missing X-Session-Id or invalid body'),
            new OA\Response(response: 429, description: 'Too many messages from this session — slow down'),
        ]
    )]
    public function message(Request $request): JsonResponse
    {
        $sessionId = trim((string) $request->headers->get('X-Session-Id'));
        if ('' === $sessionId) {
            return $this->json(['error' => 'X-Session-Id header is required'], Response::HTTP_BAD_REQUEST);
        }

        $dto = $this->dtoParser->parse($request, ChatMessageRequest::class);

        if ($this->chatbotService->isRateLimited($sessionId)) {
            return $this->json(
                ['error' => 'Too many messages — please wait a moment before sending another.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $reply = $this->chatbotService->reply($sessionId, $dto->message, $dto->history);

        return $this->json(['reply' => $reply]);
    }
}
