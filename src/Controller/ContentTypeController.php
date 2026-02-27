<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\CmsBundle\Entity\ContentType;
use ChamberOrchestra\CmsBundle\Form\Dto\ContentTypeDto;
use ChamberOrchestra\CmsBundle\Form\Type\ContentTypeType;
use ChamberOrchestra\CmsBundle\Processor\CrudProcessor;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/content-type', name: 'cms_content_type_')]
class ContentTypeController extends AbstractCrudController
{
    use SupportsLcudOperation;

    public function __construct(CrudProcessor $processor)
    {
        parent::__construct($processor, [
            'class' => ContentType::class,
            'form_class' => ContentTypeType::class,
            'data_class' => ContentTypeDto::class,
            'view_prefix' => '@ChamberOrchestraCms/content/type',
            'export' => null,
            'meta' => null,
            'move' => null,
            'index' => [
                'fields' => ['name', 'createdDatetime'],
            ],
        ]);
    }
}
