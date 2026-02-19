<?php
declare(strict_types=1);

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
        CmsRuntime::configure($this->buildId);
    }
}