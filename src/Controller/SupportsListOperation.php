<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsListOperation // @phpstan-ignore trait.unused
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(?object $parent = null): Response|ViewInterface
    {
        return $this->list(['parent' => $parent]);
    }
}
