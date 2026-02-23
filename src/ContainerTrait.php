<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle;

use ChamberOrchestra\FormBundle\FormTrait;
use ChamberOrchestra\PaginationBundle\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @phpstan-require-extends AbstractController
 */
trait ContainerTrait // @phpstan-ignore trait.unused
{
    use FormTrait;
    use PaginationTrait;
}
