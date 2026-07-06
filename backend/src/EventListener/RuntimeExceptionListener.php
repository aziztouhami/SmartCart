<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts a business-rule \RuntimeException — thrown throughout the
 * Service layer as `throw new \RuntimeException($message, $httpStatusCode)`
 * — into the same {"error": $message} JSON response every API controller
 * used to build by hand in its own try/catch. Only applies to /api/ routes;
 * anything else falls through to Symfony's normal error handling.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
class RuntimeExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof \RuntimeException) {
            return;
        }

        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $code = $exception->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : Response::HTTP_BAD_REQUEST;

        $event->setResponse(new JsonResponse(['error' => $exception->getMessage()], $status));
    }
}
