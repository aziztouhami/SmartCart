<?php

namespace App\Controller\Documentation;

use OpenApi\Generator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class DocumentationController
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    #[Route('/api/docs.json', name: 'api_docs_json', methods: ['GET'])]
    public function spec(): JsonResponse
    {
        $openapi = Generator::scan([$this->kernel->getProjectDir().'/src']);

        return new JsonResponse(json_decode($openapi->toJson(), true));
    }

    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function ui(): Response
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>SmartCart API</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            <style>body { margin: 0; }</style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: '/api/docs.json',
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                    layout: 'BaseLayout',
                    persistAuthorization: true
                });
            </script>
        </body>
        </html>
        HTML;

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }
}
