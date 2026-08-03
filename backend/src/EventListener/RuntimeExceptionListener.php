<?php

namespace App\EventListener;

use App\Domain\Exception\ApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts a business-rule \RuntimeException — thrown throughout the
 * Service layer as `throw new \RuntimeException($message, $httpStatusCode)`
 * — into the same {"error": $message} JSON response every API controller
 * used to build by hand in its own try/catch. Only applies to /api/ routes;
 * anything else falls through to Symfony's normal error handling.
 *
 * ApiException (a \RuntimeException subtype) additionally carries a
 * machine-readable `code`, added to the payload when present — e.g. so the
 * frontend can distinguish "unverified email" from other 403s.
 *
 * Framework exceptions implementing HttpExceptionInterface (e.g. the
 * AccessDeniedHttpException the security firewall rewraps a 403 into, or
 * NotFoundHttpException) are deliberately skipped: they carry their real
 * status via getStatusCode(), not the base \Exception::getCode() this
 * listener reads — handling them here would silently downgrade a correct
 * 403/404/etc. to 400. They're already handled correctly by Symfony's own
 * error listener.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
class RuntimeExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof HttpExceptionInterface) {
            return;
        }

        if (!$exception instanceof \RuntimeException) {
            return;
        }

        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $code = $exception->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : Response::HTTP_BAD_REQUEST;

        $payload = ['error' => $exception->getMessage()];
        if ($exception instanceof ApiException && null !== $exception->getApiCode()) {
            $payload['code'] = $exception->getApiCode();
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }
}
