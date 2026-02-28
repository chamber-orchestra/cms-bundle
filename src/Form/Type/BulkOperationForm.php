<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\BulkOperationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BulkOperationForm extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return '';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entities', HiddenType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('all', HiddenType::class, [
                'label' => false,
                'required' => false,
            ]);

        $builder->get('all')->addModelTransformer(new CallbackTransformer(static fn (?bool $v) => (int) $v, static fn (?string $v) => (bool) $v));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BulkOperationDto::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'bulk_operation',
            'allow_extra_fields' => true,
        ]);
    }
}
