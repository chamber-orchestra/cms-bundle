<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use ChamberOrchestra\CmsBundle\Api\HasContentEntriesInterface;
use ChamberOrchestra\CmsBundle\Api\View\ContentEntryView;
use ChamberOrchestra\CmsBundle\Repository\ContentEntryRepository;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use ChamberOrchestra\ViewBundle\View\IterableView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;

#[AsEventListener(ViewEvent::class, priority: 10)]
class ContentEntriesViewSubscriber
{
    public function __construct(
        private readonly ContentEntryRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof HasContentEntriesInterface) {
            return;
        }

        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }

        $entries = $this->repository->findByOwnerId($result->getOwnerId());

        $result->setContent(new IterableView($entries, ContentEntryView::class));
    }
}
