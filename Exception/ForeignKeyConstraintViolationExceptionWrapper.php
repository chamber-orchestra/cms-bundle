<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Exception;

use Throwable;

class ForeignKeyConstraintViolationExceptionWrapper extends \RuntimeException implements ExceptionInterface
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Foreign key constraint violation error occured.', $previous->getCode(), $previous);
    }
}