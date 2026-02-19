<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @mixin AbstractCrudController
 */
trait SupportsListOperation
{
    #[Route("", name: "index", methods: ["GET"])]
    public function index(?object $parent = null): Response|ViewInterface
    {
        return $this->list(['parent' => $parent]);
    }
}