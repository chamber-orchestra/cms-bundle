<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

use ChamberOrchestra\CmsBundle\Audit\Attribute\AuditTransform;

/**
 * Writes a value in the form the entity asked for (`#[AuditTransform]`): a mask, a digest, a digest with the secret.
 */
final readonly class AuditTransformer
{
    public const string MASK = '***';

    public function __construct(private string $keyedHashSecret)
    {
    }

    public function apply(string $transformation, mixed $value): mixed
    {
        return match ($transformation) {
            AuditTransform::HIDDEN => self::MASK,
            AuditTransform::HASH => null === $value ? null : 'sha256:'.\substr(\hash('sha256', self::text($value)), 0, 16),
            AuditTransform::KEYED_HASH => null === $value ? null : 'hmac:'.\substr(\hash_hmac('sha256', self::text($value), $this->keyedHashSecret), 0, 16),
            default => throw new \LogicException(\sprintf('Unknown audit transformation "%s".', $transformation)),
        };
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            \is_scalar($value) || $value instanceof \Stringable => (string) $value,
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) \json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE),
        };
    }
}
