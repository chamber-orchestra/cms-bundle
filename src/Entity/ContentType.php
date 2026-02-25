<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Entity;

use ChamberOrchestra\CmsBundle\Repository\ContentTypeRepository;
use ChamberOrchestra\DoctrineClockBundle\Entity\TimestampTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Contracts\Entity\SoftDeleteInterface;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\GeneratedIdTrait;
use ChamberOrchestra\DoctrineExtensionsBundle\Entity\SoftDeleteTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentTypeRepository::class)]
#[ORM\ChangeTrackingPolicy(value: 'DEFERRED_EXPLICIT')]
class ContentType implements SoftDeleteInterface
{
    use GeneratedIdTrait;
    use TimestampTrait;
    use SoftDeleteTrait;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: 'json', nullable: false)]
    private array $schema = [];

    /** @var Collection<int, ContentEntry> */
    #[ORM\OneToMany(targetEntity: ContentEntry::class, mappedBy: 'contentType', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $entries;

    public function __construct(string $name = '')
    {
        $this->name = $name;
        $this->entries = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @return array<int, array<string, mixed>> */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /** @param array<int, array<string, mixed>> $schema */
    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    /** @return Collection<int, ContentEntry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }
}
