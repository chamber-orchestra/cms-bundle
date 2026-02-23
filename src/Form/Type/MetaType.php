<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\MetaDto;
use ChamberOrchestra\MetaBundle\Entity\Helper\RobotsBehaviour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MetaType extends AbstractType
{
    private const int MAX_STRING_LENGTH = 255;
    private const int MAX_DESCRIPTION_LENGTH = 160;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MetaDto::class,
            'translation_domain' => 'cms',
            'label_format' => 'meta.field.%name%',
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metaImage', ImageType::class, [
                'required' => false,
                'constraints' => [
                    new Image(),
                ],
            ])
            ->add('title', TextType::class, [
                'required' => true,
                'attr' => ['maxlength' => self::MAX_STRING_LENGTH],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: self::MAX_STRING_LENGTH),
                ],
            ])
            ->add('robotsBehaviour', EnumType::class, [
                'class' => RobotsBehaviour::class, // @phpstan-ignore class.notFound
                'required' => true,
                'choice_label' => static fn (RobotsBehaviour $case): string => match ($case) { // @phpstan-ignore class.notFound, match.unhandled
                    RobotsBehaviour::IndexFollow => 'robots_behaviour.indexfollow', // @phpstan-ignore class.notFound
                    RobotsBehaviour::IndexNoFollow => 'robots_behaviour.indexnofollow', // @phpstan-ignore class.notFound
                    RobotsBehaviour::NoIndexFollow => 'robots_behaviour.noindexfollow', // @phpstan-ignore class.notFound
                    RobotsBehaviour::NoIndexNoFollow => 'robots_behaviour.noindexnofollow', // @phpstan-ignore class.notFound
                },
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('metaTitle', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => self::MAX_STRING_LENGTH],
                'constraints' => [
                    new Length(max: self::MAX_STRING_LENGTH),
                ],
            ])
            ->add('metaDescription', TextareaType::class, [
                'required' => false,
                'attr' => ['data-maxlength' => self::MAX_DESCRIPTION_LENGTH, 'rows' => 3],
                'constraints' => [
                    new Length(max: self::MAX_DESCRIPTION_LENGTH),
                ],
            ])
            ->add('metaKeywords', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => self::MAX_STRING_LENGTH],
                'constraints' => [
                    new Length(max: self::MAX_STRING_LENGTH),
                ],
            ]);
    }
}
