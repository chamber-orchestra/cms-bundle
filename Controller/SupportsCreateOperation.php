<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsCreateOperation
{
    #[Route("/create", name: "create", methods: ["GET", "POST"])]
    public function create(?object $parent = null): Response|ViewInterface
    {
        return $this->doCreate(['parent' => $parent]);
    }
}