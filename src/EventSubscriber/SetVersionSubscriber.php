<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use ChamberOrchestra\CmsBundle\Twig\CmsRuntime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

readonly class SetVersionSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $buildId)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [RequestEvent::class => 'onRequest'];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        CmsRuntime::configure($this->buildId);
    }
}
