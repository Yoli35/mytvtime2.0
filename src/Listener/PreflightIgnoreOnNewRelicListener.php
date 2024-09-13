<?php
namespace App\Listener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
class PreflightIgnoreOnNewRelicListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!extension_loaded('newrelic')) {
            return;
        }

        if ('OPTIONS' === $event->getRequest()->getMethod()) {
            newrelic_ignore_transaction();
        }
    }
}