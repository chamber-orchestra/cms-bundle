<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EntityRepository\Helper;

readonly class SortHelper extends AbstractHelper
{
    /**
     * @param array<string, mixed> $orderBy
     * @param array<string, mixed> $mapping
     */
    public function __invoke(array $orderBy = [], array $mapping = []): void
    {
        foreach ($orderBy as $k => $v) {
            if (null === $v) {
                continue;
            }

            if ($map = $mapping[$k] ?? null) {
                if (!\is_string($map) && \is_callable($map)) {
                    $this->call($map, $v, $orderBy);
                    continue;
                }

                if (\is_string($map)) {
                    $k = $map;
                }
            }
            if (\is_string($v)) {
                $this->qb->addOrderBy($this->alias($k), $v);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function call(callable $callable, mixed $value, array $data): void
    {
        \call_user_func($callable, $this->qb, $value, $data);
    }
}
