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
trait SupportsDeleteOperation // @phpstan-ignore trait.unused
{
    #[Route('/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(?object $parent = null): Response|ViewInterface
    {
        return $this->doDelete(['parent' => $parent]);
    }

    #[Route('/bulk-delete', name: 'bulk_delete', methods: ['DELETE'])]
    public function bulkDelete(?object $parent = null): Response|ViewInterface
    {
        return $this->doBulkDelete(['parent' => $parent]);
    }
}
