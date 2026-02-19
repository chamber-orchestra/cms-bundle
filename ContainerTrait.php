<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle;

use ChamberOrchestra\FormBundle\FormTrait;
use ChamberOrchestra\PaginationBundle\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @mixin AbstractController
 */
trait ContainerTrait
{
    use FormTrait;
    use PaginationTrait;
}