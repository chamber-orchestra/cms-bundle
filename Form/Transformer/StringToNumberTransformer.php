<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

readonly class StringToNumberTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        return (string) $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $value = \preg_replace('~\D~', '', (string) $value);

        return '' === $value ? null : (int) $value;
    }
}
