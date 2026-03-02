<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Entity;

use ChamberOrchestra\CmsBundle\Repository\ContentEntryRepository;
use ChamberOrchestra\DoctrineClockBundle\Entity\TimestampTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\GeneratedIdTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\SoftDeleteTrait;
use ChamberOrchestra\DoctrineSortBundle\Contracts\Entity\SortInterface;
use ChamberOrchestra\DoctrineSortBundle\Entity\SortTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentEntryRepository::class)]
#[ORM\ChangeTrackingPolicy(value: 'DEFERRED_EXPLICIT')]
class ContentEntry implements SoftDeleteInterface, SortInterface
{
    use GeneratedIdTrait;
    use SoftDeleteTrait;
    use SortTrait;
    use TimestampTrait;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', nullable: false)]
    private array $data = [];

    #[ORM\ManyToOne(targetEntity: ContentType::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ContentType $contentType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ownerClass = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $ownerId = null;

    public function __construct(ContentType $contentType)
    {
        $this->contentType = $contentType;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function setContentType(ContentType $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getOwnerClass(): ?string
    {
        return $this->ownerClass;
    }

    public function setOwnerClass(?string $ownerClass): void
    {
        $this->ownerClass = $ownerClass;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }
}
