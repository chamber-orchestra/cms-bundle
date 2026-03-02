<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Twig;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\CmsBundle\Repository\ContentEntryRepository;
use ChamberOrchestra\DoctrineExtensionsBundle\Filter\SoftDeleteFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

class ContentRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ContentEntryRepository $entryRepository,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getEntry(string $id): ?ContentEntry
    {
        $this->enableSoftDelete();

        $result = $this->entryRepository->find(Uuid::fromString($id));

        return $result instanceof ContentEntry ? $result : null;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<ContentEntry>
     */
    public function getEntriesFor(object $owner, array $options = []): array
    {
        $this->enableSoftDelete();

        /** @var array<string, string> $orderBy */
        $orderBy = \is_array($options['orderBy'] ?? null) ? $options['orderBy'] : ['sortOrder' => 'ASC'];

        $type = $options['type'] ?? null;
        if (\is_string($type)) {
            return $this->entryRepository->findByOwnerAndTypeName($owner, $type, $orderBy);
        }

        return $this->entryRepository->findByOwner($owner, $orderBy);
    }

    private function enableSoftDelete(): void
    {
        if (\class_exists(SoftDeleteFilter::class)) {
            $this->em->getFilters()->enable(SoftDeleteFilter::class);
        }
    }

    public function renderEntry(ContentEntry $entry): string
    {
        $typeName = $entry->getContentType()->getName();
        $customTemplate = 'content/blocks/'.$typeName.'.html.twig';

        $template = $this->twig->getLoader()->exists($customTemplate)
            ? $customTemplate
            : '@ChamberOrchestraCms/content/blocks/_generic.html.twig';

        return $this->twig->render($template, ['entry' => $entry]);
    }
}
