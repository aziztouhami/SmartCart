<?php

namespace App\Controller\Product;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Product\TrackGuestEventRequest;
use App\Entity\GuestEvent;
use App\Repository\ProductRepository;
use App\Service\GuestEventService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lets a visitor browsing without an account be tracked by a client-generated
 * session id instead of a user id, so the same "viewed/added together" signal
 * that powers logged-in recommendations also works for guests.
 */
#[Route('/api/guest/events', name: 'api_guest_events_')]
#[OA\Tag(name: 'Guest Events', description: 'Anonymous session tracking for guest recommendations')]
class GuestEventController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private GuestEventService $guestEventService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    /**
     * Record a guest's view or cart-add for a product under their session id.
     */
    #[Route('', name: 'track', methods: ['POST'])]
    #[OA\Post(
        path: '/api/guest/events',
        operationId: 'trackGuestEvent',
        summary: 'Track a guest session event',
        parameters: [new OA\Parameter(name: 'X-Session-Id', in: 'header', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['productId', 'type'],
                properties: [
                    new OA\Property(property: 'productId', type: 'integer'),
                    new OA\Property(property: 'type', type: 'string', enum: GuestEvent::TYPES),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Event recorded'),
            new OA\Response(response: 400, description: 'Missing session id or invalid type'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function track(Request $request): JsonResponse
    {
        $sessionId = trim((string) $request->headers->get('X-Session-Id'));
        if ('' === $sessionId) {
            return $this->json(['error' => 'X-Session-Id header is required'], Response::HTTP_BAD_REQUEST);
        }

        $dto = $this->dtoParser->parse($request, TrackGuestEventRequest::class);

        $product = $this->productRepository->find($dto->productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $event = $this->guestEventService->track($sessionId, $product, $dto->type);

        return $this->json(['id' => $event->getId()], Response::HTTP_CREATED);
    }
}
