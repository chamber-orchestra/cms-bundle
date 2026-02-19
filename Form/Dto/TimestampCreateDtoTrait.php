<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Dto;

use Symfony\Component\Clock\DatePoint;

trait TimestampCreateDtoTrait
{
    public DatePoint|null $createdDatetime = null;
}
