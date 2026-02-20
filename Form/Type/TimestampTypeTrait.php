<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

trait TimestampTypeTrait
{
    use TimestampCreateTypeTrait;
    use TimestampUpdateTypeTrait;

    private function addTimestampChildren(FormBuilderInterface $builder, array $options): void
    {
        $this->addTimestampCreateChildren($builder, $options);
        $this->addTimestampUpdateChildren($builder, $options);
    }
}
