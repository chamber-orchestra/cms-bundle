<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\EntityRepository\Helper;

readonly class FilterHelper extends AbstractHelper
{
    public function __invoke(array $data = [], array $mapping = []): void
    {
        foreach ($data as $name => $value) {
            if (null === $value) {
                continue;
            }

            if ($map = $mapping[$name] ?? null) {
                if (!\is_string($map) && \is_callable($map)) {
                    $this->call($map, $value, $data);
                    continue;
                }

                if (\is_string($map)) {
                    $name = $map;
                }
            }

            if (\is_iterable($value)) {
                $this->eq($name, \is_array($value) ? $value : \iterator_to_array($value));
                continue;
            }

            if (\is_object($value) || \is_bool($value) || \is_int($value) || \is_float($value)) {
                $this->eq($name, $value);
                continue;
            }

            if (\is_string($value)) {
                $this->like($name, $value);
                continue;
            }
        }
    }

    private function call(callable $callable, mixed $value, array $data): void
    {
        \call_user_func($callable, $this->qb, $value, $data);
    }

    private function eq(string $field, mixed $value): void
    {
        $p = $this->param($field);
        if (\is_array($value)) {
            $this->qb
                ->andWhere($this->qb->expr()->in($this->alias($field), ':'.$p))
                ->setParameter($p, $value);
        } else {
            $this->qb
                ->andWhere($this->qb->expr()->eq($this->alias($field), ':'.$p))
                ->setParameter($p, $value);
        }
    }

    private function like(string $field, string $value): void
    {
        $this->qb
            ->andWhere($this->qb->expr()->like('LOWER('.$this->alias($field).')', ':'.($p = $this->param($field))))
            ->setParameter($p, '%'.\mb_strtolower($value).'%');
    }
}