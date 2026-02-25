<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsToggleOperation // @phpstan-ignore trait.unused
{
    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(ToggleInterface|string|int $id, ?object $parent = null): Response|ViewInterface
    {
        if (\is_object($id)) {
            return $this->doToggle($id, ['parent' => $parent]);
        }

        if (!isset($this->options['class'])) {
            throw new \RuntimeException('To support traits you should specify "class" option in the configuration');
        }

        /** @var ServiceEntityRepositoryInterface $er */
        $er = $this->em->getRepository($this->options['class']);
        $entity = $er->find($id);

        if (null === $entity) {
            throw $this->createNotFoundException(\sprintf('Entity "%s" with id "%s" not found.', $this->options['class'], $id));
        }

        return $this->doToggle($entity, ['parent' => $parent]);
    }
}
