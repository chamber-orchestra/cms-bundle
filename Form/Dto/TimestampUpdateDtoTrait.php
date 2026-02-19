<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Symfony\Component\Clock\DatePoint;

trait TimestampUpdateDtoTrait
{
    public DatePoint|null $updatedDatetime = null;
}
