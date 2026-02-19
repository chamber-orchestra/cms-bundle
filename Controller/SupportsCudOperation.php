<?php

namespace ChamberOrchestra\CmsBundle\Controller;

/**
 * @mixin AbstractCrudController
 */
trait SupportsCudOperation
{
    use SupportsCreateOperation;
    use SupportsUpdateOperation;
    use SupportsDeleteOperation;
}