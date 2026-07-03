<?php

namespace App\Controller\Profile;

use App\DTO\Profile\ChangePasswordRequest;
use App\DTO\Profile\UpdateProfileRequest;
use App\Entity\User;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/profile', name: 'api_profile_')]
#[OA\Tag(name: 'Profile', description: 'User profile management — requires authentication')]
class ProfileController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Get the authenticated user's full profile.
     */
    #[Route('', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        operationId: 'getProfile',
        summary: 'Get profile',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'User profile')]
    )]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->buildProfileArray($user));
    }

    /**
     * Update profile fields (partial — only supplied fields change).
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/profile',
        operationId: 'updateProfile',
        summary: 'Update profile',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'firstName', type: 'string'),
                    new OA\Property(property: 'lastName', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated profile'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 409, description: 'Email already in use'),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateProfileRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userService->updateProfile($user, $dto);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->buildProfileArray($user));
    }

    /**
     * Change the authenticated user's password.
     */
    #[Route('/password', name: 'change_password', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/profile/password',
        operationId: 'changePassword',
        summary: 'Change password',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['currentPassword', 'newPassword'],
                properties: [
                    new OA\Property(property: 'currentPassword', type: 'string', format: 'password'),
                    new OA\Property(property: 'newPassword', type: 'string', format: 'password', minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password changed'),
            new OA\Response(response: 400, description: 'Wrong current password or validation error'),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), ChangePasswordRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->userService->changePassword($user, $dto);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Password updated successfully']);
    }

    /**
     * Request deletion of the authenticated user's account. The account is
     * scheduled for permanent deletion in 30 days; logging back in before
     * then automatically cancels the deletion.
     */
    #[Route('', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/profile',
        operationId: 'requestAccountDeletion',
        summary: 'Request account deletion (30-day grace period)',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Deletion scheduled')]
    )]
    public function delete(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $this->userService->requestDeletion($user);

        return $this->json([
            'message' => 'Your account is scheduled for deletion in 30 days. Log back in before then to cancel it.',
        ]);
    }

    private function buildProfileArray(User $user): array
    {
        return [
            'id'                   => $user->getId(),
            'email'                => $user->getEmail(),
            'firstName'            => $user->getFirstName(),
            'lastName'             => $user->getLastName(),
            'phone'                => $user->getPhone(),
            'roles'                => $user->getRoles(),
            'marketingOptIn'       => $user->getMarketingOptIn(),
            'preferredCategoryIds' => $user->getPreferredCategoryIds(),
            'preferredBrandIds'    => $user->getPreferredBrandIds(),
            'deletionRequestedAt'  => $user->getDeletionRequestedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt'            => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'            => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
