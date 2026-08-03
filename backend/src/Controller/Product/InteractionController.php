<?php

namespace App\Controller\Product;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Product\TrackInteractionRequest;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\InteractionService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Products', description: 'User behavior tracking')]
class InteractionController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private InteractionService $interactionService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    /**
     * Record a user interaction with a product (view, cart add, purchase, rating).
     * Used by the frontend to feed recommendation data.
     */
    #[Route('/api/products/{id}/interact', name: 'api_products_interact', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/products/{id}/interact',
        operationId: 'trackInteraction',
        summary: 'Track a product interaction',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['view', 'cart', 'purchase', 'rating'],
                    ),
                    new OA\Property(
                        property: 'value',
                        type: 'integer',
                        nullable: true,
                        description: 'Optional integer value (e.g. star rating 1-5)',
                    ),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Interaction recorded'),
            new OA\Response(response: 400, description: 'Invalid type'),
            new OA\Response(response: 401, description: 'Authentication required'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function track(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, TrackInteractionRequest::class);
        $interaction = $this->interactionService->track($user, $product, $dto->type, $dto->value);

        return $this->json([
            'id' => $interaction->getId(),
            'type' => $interaction->getType(),
            'productId' => $product->getId(),
        ], Response::HTTP_CREATED);
    }
}
