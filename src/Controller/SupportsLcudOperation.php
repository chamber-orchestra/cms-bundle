<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

/**
 * @mixin AbstractCrudController
 */
trait SupportsLcudOperation // @phpstan-ignore trait.unused
{
    use SupportsCreateOperation;
    use SupportsDeleteOperation;
    use SupportsListOperation;
    use SupportsUpdateOperation;
}
