<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EntityRepository\Helper;

use Doctrine\ORM\QueryBuilder;

abstract readonly class AbstractHelper
{
    protected string $root;

    public function __construct(protected QueryBuilder $qb)
    {
        $this->root = \current($qb->getRootAliases()) ?: '';
    }

    protected function alias(string $field): string
    {
        if (!\preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field)) {
            throw new \InvalidArgumentException(\sprintf('Invalid field name: "%s".', $field));
        }

        return $this->root.'.'.$field;
    }

    protected function param(string $field): string
    {
        return $field.'_'.\bin2hex(\random_bytes(4));
    }
}
