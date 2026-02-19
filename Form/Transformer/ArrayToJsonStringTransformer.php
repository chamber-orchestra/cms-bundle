<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

readonly class ArrayToJsonStringTransformer implements DataTransformerInterface
{
    public function transform($value): string
    {
        if (null !== $value && !\is_array($value)) {
            throw new TransformationFailedException('Expected a array or null.');
        }

        if (null === $value) {
            return '';
        }

        return (string) \json_encode($value, \JSON_PRETTY_PRINT);
    }

    public function reverseTransform($value): ?array
    {
        if (null !== $value && !\is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        if (!$value) {
            return null;
        }

        $result = \json_decode($value, true);

        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new TransformationFailedException(\sprintf('Could not parse string to valid JSON format. %s', \json_last_error_msg()));
        }

        return $result;
    }
}
