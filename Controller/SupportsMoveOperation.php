<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsMoveOperation
{
    #[Route("/{id}/move/{direction<up|down>}", name: "move", methods: ["POST"])]
    public function move(object|string|int $id, string $direction): Response|ViewInterface
    {
        if (\is_object($id)) {
            return $this->doMove($id, $direction);
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

        return $this->doMove($entity, $direction);
    }
}