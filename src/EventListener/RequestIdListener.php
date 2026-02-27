<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: 'kernel.response')]
final class RequestIdListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response->headers->has('X-Request-ID')) {
            $response->headers->set('X-Request-ID', Uuid::v4()->toRfc4122());
        }
    }
}
