<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\EntityRepository\Helper;

readonly class SortHelper extends AbstractHelper
{
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

    private function call(callable $callable, mixed $value, array $data): void
    {
        \call_user_func($callable, $this->qb, $value, $data);
    }
}