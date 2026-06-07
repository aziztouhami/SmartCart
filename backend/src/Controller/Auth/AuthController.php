<?php

namespace App\Controller\Auth;

use App\DTO\Auth\LoginRequest;
use App\DTO\Auth\LoginResponse;
use App\DTO\Auth\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
#[OA\Tag(name: 'Authentication', description: 'User authentication and account management')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        operationId: 'login',
        summary: 'User Login',
        description: 'Authenticate user with email and password to receive JWT token',
        requestBody: new OA\RequestBody(
            description: 'Login credentials',
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                        new OA\Property(property: 'expiresIn', type: 'integer', example: 3600),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'firstName', type: 'string'),
                            new OA\Property(property: 'lastName', type: 'string'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function login(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticationService $authService,
    ): JsonResponse {
        try {
            // Parse request
            $loginRequest = $serializer->deserialize(
                $request->getContent(),
                LoginRequest::class,
                'json'
            );

            // Validate
            $errors = $validator->validate($loginRequest);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            // Find user
            $user = $userRepository->findOneBy(['email' => $loginRequest->email]);
            if (!$user) {
                return $this->json(
                    ['error' => 'Invalid email or password'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Verify password
            if (!$passwordHasher->isPasswordValid($user, $loginRequest->password)) {
                return $this->json(
                    ['error' => 'Invalid email or password'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Create and return JWT response
            $response = $authService->createLoginResponse($user);

            return $this->json($response, Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An error occurred during login: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        operationId: 'register',
        summary: 'User Registration',
        description: 'Register a new user account and receive JWT token',
        requestBody: new OA\RequestBody(
            description: 'Registration details',
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'newuser@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'securePassword123'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'expiresIn', type: 'integer'),
                        new OA\Property(property: 'user', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'firstName', type: 'string'),
                            new OA\Property(property: 'lastName', type: 'string'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 409, description: 'Email already in use'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        AuthenticationService $authService,
    ): JsonResponse {
        try {
            // Parse request
            $registerRequest = $serializer->deserialize(
                $request->getContent(),
                RegisterRequest::class,
                'json'
            );

            // Validate
            $errors = $validator->validate($registerRequest);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            // Check if user exists
            if ($userRepository->findOneBy(['email' => $registerRequest->email])) {
                return $this->json(
                    ['error' => 'Email already in use'],
                    Response::HTTP_CONFLICT
                );
            }

            // Create user
            $user = new User();
            $user->setEmail($registerRequest->email);
            $user->setFirstName($registerRequest->firstName);
            $user->setLastName($registerRequest->lastName);
            $user->setPassword(
                $passwordHasher->hashPassword($user, $registerRequest->password)
            );
            $user->setRoles(['ROLE_USER']);

            $em->persist($user);
            $em->flush();

            // Return JWT response
            $response = $authService->createLoginResponse($user);

            return $this->json($response, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An error occurred during registration: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(
        operationId: 'getCurrentUser',
        summary: 'Get Current User',
        description: 'Retrieve information about the currently authenticated user',
        security: [['Bearer' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user information',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized - Invalid or missing token'),
        ]
    )]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(
        operationId: 'logout',
        summary: 'User Logout',
        description: 'Logout the current user. JWT tokens are stateless, so this is a client-side operation.',
        security: [['Bearer' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
                    ]
                )
            ),
        ]
    )]
    public function logout(): JsonResponse
    {
        // JWT tokens are stateless, so logout is just a client-side action
        // The client should remove the token from storage
        return $this->json(['message' => 'Logged out successfully']);
    }
}
