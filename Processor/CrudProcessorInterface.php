<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;

interface CrudProcessorInterface
{
    public function create(DtoInterface $dto): object;

    public function update(DtoInterface $dto, object $entity): void;

    public function updateEmbeddable(DtoInterface $dto, object $entity, object $embeddable): void;

    public function delete(DtoInterface $dto, object $entity): void;

    public function sync(object $source, object $target): void;
}