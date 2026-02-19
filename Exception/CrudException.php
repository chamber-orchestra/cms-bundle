<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Exception;

class CrudException extends RuntimeException
{
    public static function createNotValidType(object $object, string $field, $value, array $expected): self
    {
        return new self(\sprintf('Target value of %s::$%s must be of type "%s", "%s" given.', \get_class($object), $field, \implode('|', $expected), \is_object($value) ? \get_class($value) : \gettype($value)));
    }
}