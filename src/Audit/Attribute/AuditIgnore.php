<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit\Attribute;

/**
 * A property of an `#[Auditable]` entity whose value is not written at all (not even as a mask): a change of it alone writes no line. For what changes all the time and says nothing
 * (a sort order, a counter).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class AuditIgnore
{
}
