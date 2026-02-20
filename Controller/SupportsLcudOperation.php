<?php

namespace ChamberOrchestra\CmsBundle\Controller;

/**
 * @mixin AbstractCrudController
 */
trait SupportsLcudOperation
{
    use SupportsListOperation;
    use SupportsCreateOperation;
    use SupportsUpdateOperation;
    use SupportsDeleteOperation;
}