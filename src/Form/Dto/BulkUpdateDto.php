<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Dto;

/**
 * Change the selected rows of a list at once: which rows (`entities`, comma-separated ids, or `all`) and the values to give them (only the fields the form filled).
 */
class BulkUpdateDto
{
    public ?string $entities = null;
    public ?bool $all = false;
    /** The address query of the list (its filter and column search): what "all" means when every row of the filter is chosen. */
    public ?string $query = null;

    /** @var array<string, mixed> */
    public array $values = [];
}
