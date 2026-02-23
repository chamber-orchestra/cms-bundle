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
trait SupportsExportOperation // @phpstan-ignore trait.unused
{
    #[Route('/export', name: 'export', methods: ['GET', 'POST'])]
    public function export(?object $parent = null): Response|ViewInterface
    {
        if (!isset($this->options['class'])) {
            throw new \RuntimeException('To support traits you should specify "class" option in the configuration');
        }

        return $this->doExport(['parent' => $parent]);
    }
}
