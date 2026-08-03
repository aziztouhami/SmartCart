<?php

namespace App\Controller\Admin;

use App\Service\FileUploadService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Uploads', description: 'Generic file upload (admin/internal use)')]
class ImageController extends AbstractController
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {
    }

    #[Route('/upload', name: 'api_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/upload',
        operationId: 'uploadFile',
        summary: 'Upload a generic image file',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'File uploaded, public URL returned'),
            new OA\Response(response: 400, description: 'No file provided or validation error'),
        ]
    )]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $relativeUrl = $this->fileUploadService->upload(
            $file,
            '',
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            5 * 1024 * 1024,
        );

        return $this->json(
            ['url' => $request->getSchemeAndHttpHost().$relativeUrl],
            Response::HTTP_CREATED
        );
    }
}
