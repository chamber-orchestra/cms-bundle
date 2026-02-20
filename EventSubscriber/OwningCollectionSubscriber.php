<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class OwningCollectionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
