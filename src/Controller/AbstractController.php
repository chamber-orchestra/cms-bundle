<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\CmsBundle\ContainerTrait;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationFactory;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationInterface;
use ChamberOrchestra\PaginationBundle\Pagination\Type\RangeType;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    use ContainerTrait;

    public static function getSubscribedServices(): array
    {
        return \array_merge(parent::getSubscribedServices(), [
            'translator' => '?'.TranslatorInterface::class,
            PaginationFactory::class,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getPagination(array $options = []): PaginationInterface
    {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace([
            'limit' => 16,
            'extended' => true,
        ], $options);

        return $this->createPagination(RangeType::class, $merged);
    }
}
