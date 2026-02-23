<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;

interface CrudProcessorInterface
{
    public function create(DtoInterface $dto): object;

    public function update(DtoInterface $dto, object $entity): void;

    public function updateEmbeddable(DtoInterface $dto, object $entity, object $embeddable): void;

    public function delete(DtoInterface $dto, object $entity): void;

    /**
     * @param class-string $class
     */
    public function bulkDelete(BulkOperationDto $dto, string $class): void;

    public function toggle(ToggleInterface $entity): void;

    public function copy(object $entity): void;

    public function move(object $entity, string $direction): void;

    public function sync(object $source, object $target): void;
}
