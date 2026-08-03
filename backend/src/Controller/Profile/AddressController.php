<?php

namespace App\Controller\Profile;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Address\AddressItem;
use App\DTO\Address\CreateAddressRequest;
use App\DTO\Address\UpdateAddressRequest;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Service\AddressService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile/addresses', name: 'api_profile_addresses_')]
#[OA\Tag(name: 'Profile', description: 'Saved address management — requires authentication')]
class AddressController extends AbstractController
{
    public function __construct(
        private AddressRepository $addressRepository,
        private AddressService $addressService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/profile/addresses', operationId: 'listAddresses', summary: 'List saved addresses', security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Address list')])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $addresses = $this->addressRepository->findByUser($user);

        return $this->json(array_map(fn ($a) => AddressItem::fromEntity($a), $addresses));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/profile/addresses',
        operationId: 'createAddress',
        summary: 'Add a saved address',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['label', 'street', 'city', 'country'],
                properties: [
                    new OA\Property(property: 'label', type: 'string', example: 'Home'),
                    new OA\Property(property: 'street', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'postalCode', type: 'string'),
                    new OA\Property(property: 'country', type: 'string'),
                    new OA\Property(property: 'isDefault', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Address created'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $dto = $this->dtoParser->parse($request, CreateAddressRequest::class);
        $address = $this->addressService->create($user, $dto);

        return $this->json(AddressItem::fromEntity($address), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/profile/addresses/{id}',
        operationId: 'updateAddress',
        summary: 'Update a saved address',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'label', type: 'string'),
                    new OA\Property(property: 'street', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'postalCode', type: 'string'),
                    new OA\Property(property: 'country', type: 'string'),
                    new OA\Property(property: 'isDefault', type: 'boolean'),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Address updated'),
            new OA\Response(response: 404, description: 'Address not found'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $this->addressRepository->find($id);
        if (!$address || $address->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Address not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, UpdateAddressRequest::class);
        $address = $this->addressService->update($address, $dto, $user);

        return $this->json(AddressItem::fromEntity($address));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/profile/addresses/{id}',
        operationId: 'deleteAddress',
        summary: 'Delete a saved address',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Address deleted'),
            new OA\Response(response: 404, description: 'Address not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $this->addressRepository->find($id);
        if (!$address || $address->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Address not found'], Response::HTTP_NOT_FOUND);
        }

        $this->addressService->delete($address);

        return $this->json(['message' => 'Address deleted']);
    }
}
