<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

trait TimestampTypeTrait // @phpstan-ignore trait.unused
{
    use TimestampCreateTypeTrait;
    use TimestampUpdateTypeTrait;

    private function addTimestampChildren(FormBuilderInterface $builder, array $options): void
    {
        $this->addTimestampCreateChildren($builder, $options);
        $this->addTimestampUpdateChildren($builder, $options);
    }
}
