<?php

declare(strict_types=1);

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
