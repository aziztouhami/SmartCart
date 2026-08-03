<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'SmartCart API',
    version: '1.0.0',
    description: 'Complete REST API for SmartCart E-commerce Platform',
    contact: new OA\Contact(name: 'SmartCart Support', email: 'support@smartcart.local'),
    license: new OA\License(name: 'MIT', identifier: 'MIT')
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Development Server')]
#[OA\SecurityScheme(
    securityScheme: 'Bearer',
    type: 'http',
    description: 'JWT Bearer Token — paste your token after login',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class OpenApiConfiguration
{
}
