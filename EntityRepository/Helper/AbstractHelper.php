<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\EntityRepository\Helper;

use Doctrine\ORM\QueryBuilder;

readonly abstract class AbstractHelper
{
    protected string $root;

    public function __construct(protected QueryBuilder $qb)
    {
        $this->root = \current($qb->getRootAliases());
    }

    protected function alias(string $field): string
    {
        return $this->root.'.'.$field;
    }

    protected function param(string $field): string
    {
        static $seq = 0;

        return $field.'_p'.(++$seq);
    }
}