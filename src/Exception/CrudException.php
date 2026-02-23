<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Exception;

class CrudException extends RuntimeException
{
    /** @param list<string> $expected */
    public static function createNotValidType(object $object, string $field, mixed $value, array $expected): self
    {
        return new self(\sprintf('Target value of %s::$%s must be of type "%s", "%s" given.', $object::class, $field, \implode('|', $expected), \is_object($value) ? $value::class : \gettype($value)));
    }
}
