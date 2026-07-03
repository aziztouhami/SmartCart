<?php

namespace App\Controller\Auth;

use App\DTO\Auth\LoginRequest;
use App\DTO\Auth\RegisterRequest;
use App\Service\AuthenticationService;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
#[OA\Tag(name: 'Authentication', description: 'User authentication and account management')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private AuthenticationService $authService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private string $googleClientId = '',
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/login',
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
    public function login(Request $request): JsonResponse
    {
        if (!str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            return $this->json(['error' => 'Content-Type must be application/json'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $loginRequest = $this->serializer->deserialize($request->getContent(), LoginRequest::class, 'json');

            $errors = $this->validator->validate($loginRequest);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userService->verifyCredentials($loginRequest->email, $loginRequest->password);

            if (!$user->isVerified()) {
                return $this->json(
                    ['error' => 'Please confirm your email address before logging in.', 'code' => 'EMAIL_NOT_VERIFIED'],
                    Response::HTTP_FORBIDDEN
                );
            }

            return $this->json($this->authService->createLoginResponse($user), Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            return $this->json(
                ['error' => $e->getMessage()],
                ($code >= 400 && $code < 600) ? $code : Response::HTTP_UNAUTHORIZED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An error occurred during login: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register',
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
    public function register(Request $request): JsonResponse
    {
        try {
            $registerRequest = $this->serializer->deserialize($request->getContent(), RegisterRequest::class, 'json');

            $errors = $this->validator->validate($registerRequest);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userService->register($registerRequest);

            return $this->json([
                'message' => 'Account created. Please check your email to confirm your account before logging in.',
                'email'   => $user->getEmail(),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An error occurred during registration: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/verify-email',
        operationId: 'verifyEmail',
        summary: 'Confirm account via emailed token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'token', type: 'string'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Account confirmed'),
            new OA\Response(response: 400, description: 'Invalid or expired token'),
        ]
    )]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $token = $data['token'] ?? '';

        if (!$token) {
            return $this->json(['error' => 'Missing confirmation token'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->userService->verifyEmail($token);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Your email has been confirmed. You can now log in.']);
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/resend-verification',
        operationId: 'resendVerification',
        summary: 'Resend the account confirmation email',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', properties: [
                new OA\Property(property: 'email', type: 'string'),
            ])
        ),
        responses: [new OA\Response(response: 200, description: 'Confirmation email resent if applicable')]
    )]
    public function resendVerification(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? '';

        if ($email) {
            $this->userService->resendVerificationEmail($email);
        }

        return $this->json([
            'message' => 'If an account with that email exists and is not yet confirmed, a new confirmation link has been sent.',
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/auth/me',
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
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }

    #[Route('/google-login', name: 'api_google_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/google-login',
        operationId: 'googleLogin',
        summary: 'Login or register via Google OAuth',
        description: 'Verifies the given Google OAuth access token, then logs in (or creates) the matching account.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['accessToken'],
                properties: [new OA\Property(property: 'accessToken', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful, JWT token returned'),
            new OA\Response(response: 400, description: 'Missing access token or account creation failed'),
            new OA\Response(response: 401, description: 'Invalid or unverified Google token'),
        ]
    )]
    public function googleLogin(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true) ?? [];
        $accessToken = $data['accessToken'] ?? '';

        if (!$accessToken) {
            return $this->json(['error' => 'Missing access token'], Response::HTTP_BAD_REQUEST);
        }

        // Verify the token with Google's tokeninfo endpoint (also returns `aud` for audience check)
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$body) {
            return $this->json(['error' => 'Invalid Google token'], Response::HTTP_UNAUTHORIZED);
        }

        $tokenInfo = json_decode($body, true);

        // Verify the token was issued for this app
        if ($this->googleClientId && ($tokenInfo['aud'] ?? '') !== $this->googleClientId
            && ($tokenInfo['azp'] ?? '') !== $this->googleClientId) {
            return $this->json(['error' => 'Token was not issued for this application'], Response::HTTP_UNAUTHORIZED);
        }

        if (($tokenInfo['email_verified'] ?? '') !== 'true' && ($tokenInfo['email_verified'] ?? false) !== true) {
            return $this->json(['error' => 'Google email is not verified'], Response::HTTP_UNAUTHORIZED);
        }

        // Fetch the full profile (tokeninfo may not include name)
        $profileCh = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($profileCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($profileCh, CURLOPT_TIMEOUT, 10);
        curl_setopt($profileCh, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $profileBody = curl_exec($profileCh);
        curl_close($profileCh);

        $profile = json_decode($profileBody, true) ?: [];
        // Merge so email/verified from tokenInfo are always present
        $profile = array_merge($tokenInfo, $profile);

        try {
            $user = $this->userService->findOrCreateFromGoogle($profile);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->authService->createLoginResponse($user));
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/logout',
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
        return $this->json(['message' => 'Logged out successfully']);
    }
}
