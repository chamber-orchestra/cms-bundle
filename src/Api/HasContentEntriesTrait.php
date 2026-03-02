<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Api;

use ChamberOrchestra\ViewBundle\View\IterableView;

trait HasContentEntriesTrait // @phpstan-ignore trait.unused
{
    /**
     * Must be initialized to a non-null value before BindView::__construct()
     * so BindUtils skips syncing a string property of the same name from
     * the source entity. Populated by ContentEntriesViewSubscriber before serialization.
     * In BindView subclasses do: $this->content = new IterableView(); before parent::__construct().
     */
    public IterableView $content;

    public function getOwnerId(): string
    {
        return (string) $this->id;
    }

    public function setContent(IterableView $content): void
    {
        $this->content = $content;
    }
}
