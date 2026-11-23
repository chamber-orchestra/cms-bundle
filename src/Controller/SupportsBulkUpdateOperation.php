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
 * "Change the selected rows of the list": the plate of the selected rows offers it when the controller has the option `bulk_update: {form_class: ...}` (the form of the
 * fields that can be changed at once, every one optional).
 *
 * @mixin AbstractCrudController
 */
trait SupportsBulkUpdateOperation // @phpstan-ignore trait.unused
{
    #[Route('/bulk-update', name: 'bulk_update', methods: ['POST'])]
    public function bulkUpdate(?object $parent = null): Response|ViewInterface
    {
        return $this->doBulkUpdate(['parent' => $parent]);
    }
}
