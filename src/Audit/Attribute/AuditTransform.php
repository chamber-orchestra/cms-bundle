<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit\Attribute;

/**
 * A property of an `#[Auditable]` entity whose value is written in another form: the line says that it changed, the value is not readable.
 *
 *     #[AuditTransform(AuditTransform::HIDDEN)]       // "***"
 *     private string $password;
 *
 *     #[AuditTransform(AuditTransform::HASH)]         // a short digest: two lines about the same value can be compared
 *     private string $phone;
 *
 *     #[AuditTransform(AuditTransform::KEYED_HASH)]   // a digest made with the secret `keyed_hash_secret`: the value cannot be guessed from it
 *     private string $email;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class AuditTransform
{
    public const string HIDDEN = 'hidden';
    public const string HASH = 'hash';
    public const string KEYED_HASH = 'keyed_hash';
    public const array TRANSFORMATIONS = [self::HIDDEN, self::HASH, self::KEYED_HASH];

    public function __construct(public string $transformation)
    {
        if (!\in_array($transformation, self::TRANSFORMATIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown audit transformation "%s": one of %s.', $transformation, \implode(', ', self::TRANSFORMATIONS)));
        }
    }
}
