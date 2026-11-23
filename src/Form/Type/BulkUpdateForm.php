<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\BulkUpdateDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of "change the selected": the rows (hidden, filled by the list) and `values`, a form of the application (`values_type`) whose fields are all optional: what is
 * left empty is not changed.
 */
class BulkUpdateForm extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'bulk_update';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string<\Symfony\Component\Form\FormTypeInterface> $type */
        $type = $options['values_type'];
        $builder
            ->add('entities', HiddenType::class, ['label' => false, 'required' => false])
            ->add('all', HiddenType::class, ['label' => false, 'required' => false])
            ->add('query', HiddenType::class, ['label' => false, 'required' => false])
            ->add('values', $type, ['label' => false, 'required' => false]);

        $builder->get('all')->addModelTransformer(new CallbackTransformer(static fn (?bool $v) => (int) $v, static fn (?string $v) => (bool) $v));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('values_type');
        $resolver->setAllowedTypes('values_type', 'string');
        $resolver->setDefaults([
            'data_class' => BulkUpdateDto::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'bulk_operation',
            'allow_extra_fields' => true,
        ]);
    }
}
