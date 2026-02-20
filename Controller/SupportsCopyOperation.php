<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

/**
 * @mixin AbstractCrudController
 */
trait SupportsCopyOperation
{
    #[Route("/{id}/copy", name: "copy", methods: ["GET"])]
    public function copy(ToggleInterface|string|int $id, ?object $parent = null): Response|ViewInterface
    {
        if (\is_object($id)) {
            return $this->doCopy($id, ['parent' => $parent]);
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

        return $this->doCopy($entity, ['parent' => $parent]);
    }
}