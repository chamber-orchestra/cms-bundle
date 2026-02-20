<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\CmsBundle\ContainerTrait;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationFactory;
use ChamberOrchestra\PaginationBundle\Pagination\PaginationInterface;
use ChamberOrchestra\PaginationBundle\Pagination\Type\RangeType;
use JetBrains\PhpStorm\Pure;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    use ContainerTrait;

    #[Pure] public static function getSubscribedServices(): array
    {
        return \array_merge(parent::getSubscribedServices(), [
            'translator' => '?'.TranslatorInterface::class,
            PaginationFactory::class,
        ]);
    }

    public function getPagination(array $options = []): PaginationInterface
    {
        return $this->createPagination(RangeType::class, \array_replace([
            'limit' => 16,
            'extended' => true,
        ], $options));
    }
}