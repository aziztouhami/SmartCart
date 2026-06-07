<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "SmartCart API",
    version: "1.0.0",
    description: "Complete REST API for SmartCart E-commerce Platform with AI-powered recommendations",
    contact: new OA\Contact(
        name: "SmartCart Support",
        email: "support@smartcart.local"
    ),
    license: new OA\License(
        name: "MIT",
        identifier: "MIT"
    )
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Development Server"
)]
#[OA\Server(
    url: "https://api.smartcart.local",
    description: "Production Server"
)]
#[OA\SecurityScheme(
    type: "http",
    description: "JWT Token",
    name: "Bearer",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApiConfiguration {}
