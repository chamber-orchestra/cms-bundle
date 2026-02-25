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

trait FileTypeTrait // @phpstan-ignore trait.unused
{
    private function addFileChild(FormBuilderInterface $builder, array $options = []): void
    {
        $builder->add('file', FileType::class, \array_replace_recursive([
            'required' => false,
            'label' => 'file.field.file',
        ], $options));
    }
}
