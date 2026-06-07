<?php

namespace App\Controller\Documentation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class DocumentationController extends AbstractController
{
    #[Route('/docs', name: 'api_docs', methods: ['GET'])]
    public function swagger(): JsonResponse
    {
        return $this->json([
            'message' => 'API Documentation available at /api/doc (Swagger UI) and /api/doc.json (OpenAPI spec)',
            'links' => [
                'swagger_ui' => '/api/doc',
                'openapi_json' => '/api/doc.json',
            ]
        ]);
    }
}
