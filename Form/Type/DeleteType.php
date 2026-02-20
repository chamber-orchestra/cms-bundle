<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Form\Type;

use ChamberOrchestra\CmsBundle\Form\Dto\DeleteDto;
use ChamberOrchestra\FormBundle\Type\HiddenEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class DeleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entity', HiddenEntityType::class, [
                'class' => $options['class'],
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('targetPath', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'DELETE',
            'class' => null,
            'data_class' => DeleteDto::class,
            'target_path' => null,
        ]);

        $resolver->setRequired('class');
    }
}