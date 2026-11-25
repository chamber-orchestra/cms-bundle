<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

/**
 * The seal of a line of the journal (`integrity.enabled`): an HMAC over what the line says, made with a secret the database does not hold. A line changed or made by hand in the
 * table does not fit its seal; `cms:audit:check` and the page of the journal say so. Disabled, it makes no seal and finds none wrong.
 */
final readonly class AuditSigner
{
    public function __construct(private bool $enabled = false, private string $secret = '')
    {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<mixed>|null $changes
     */
    public function sign(string $id, string $occurredAt, string $actor, string $action, string $class, string $entityId, string $label, ?array $changes, ?string $ip): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        return \hash_hmac('sha256', (string) \json_encode([$id, $occurredAt, $actor, $action, $class, $entityId, $label, self::canonical($changes), $ip], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE), $this->secret);
    }

    /**
     * @param array<mixed>|null $changes
     */
    public function verify(?string $signature, string $id, string $occurredAt, string $actor, string $action, string $class, string $entityId, string $label, ?array $changes, ?string $ip): bool
    {
        $expected = $this->sign($id, $occurredAt, $actor, $action, $class, $entityId, $label, $changes, $ip);

        return null !== $expected && null !== $signature && \hash_equals($expected, $signature);
    }

    /**
     * The same value however it was stored (the database keeps the keys of a JSON object in an order of its own).
     */
    private static function canonical(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        $value = \array_map(self::canonical(...), $value);
        if (!\array_is_list($value)) {
            \ksort($value);
        }

        return $value;
    }
}
