<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\CmsBundle\Entity\ContentType;

class ContentEntryDto extends AbstractDto
{
    public ContentType $contentType;
    /** @var array<string, mixed> */
    public array $data = [];
    public ?string $ownerClass = null;
    public ?string $ownerId = null;

    public function __construct(ContentType $contentType, ?object $owner = null)
    {
        parent::__construct(ContentEntry::class);
        $this->contentType = $contentType;
        if (null !== $owner) {
            $this->ownerClass = \get_class($owner);
            // @phpstan-ignore-next-line
            $this->ownerId = (string) $owner->getId();
        }
    }
}
