<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Dto;

use ChamberOrchestra\CmsBundle\Form\Dto\DtoInterface;
use Symfony\Component\Uid\Uuid;
use Tests\Integrational\Entity\TestArticle;

class TestArticleDto implements DtoInterface
{
    public ?Uuid $id = null;
    public string $title = '';
    public bool $enabled = true;
    public ?string $slug = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEntityClass(): string
    {
        return TestArticle::class;
    }
}
