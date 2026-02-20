<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsExportOperation
{
    #[Route("/export", name: "export", methods: ["GET", "POST"])]
    public function export(?object $parent = null): Response|ViewInterface
    {
        if (!isset($this->options['class'])) {
            throw new \RuntimeException('To support traits you should specify "class" option in the configuration');
        }

        return $this->doExport(['parent' => $parent]);
    }
}