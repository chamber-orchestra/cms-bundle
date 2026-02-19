<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor\Helper;

enum MoveDirection: string
{
    case UP = 'up';
    case DOWN = 'down';
}
