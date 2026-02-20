<?php

declare(strict_types=1);

namespace Tests\Integrational\Entity;

use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\ToggleInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_article')]
class TestArticle implements ToggleInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    public string $title = '';

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $slug = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function toggle(): void
    {
        $this->enabled = !$this->enabled;
    }
}
