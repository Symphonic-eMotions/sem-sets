<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Voegt CORS-headers toe aan /api/sem-sym/* routes,
 * zodat de sem-sym frontend (andere origin) er requests naar kan sturen.
 */
final class SemSymCorsSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://an.yourule.nl',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Behandel OPTIONS preflight requests direct (vóór de router).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isSemSymRoute($request)) {
            return;
        }

        if ($request->getMethod() !== Request::METHOD_OPTIONS) {
            return;
        }

        $response = new Response('', 204);
        $this->addCorsHeaders($request, $response);
        $event->setResponse($response);
    }

    /**
     * Voeg CORS-headers toe aan alle sem-sym API-responses.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isSemSymRoute($request)) {
            return;
        }

        $this->addCorsHeaders($request, $event->getResponse());
    }

    private function isSemSymRoute(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/sem-sym');
    }

    private function addCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin', '');

        $allowedOrigin = in_array($origin, self::ALLOWED_ORIGINS, true)
            ? $origin
            : self::ALLOWED_ORIGINS[0];

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Api-Key, Accept');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
