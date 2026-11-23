<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * An empty text field is submitted as null; a DTO property typed `string` (not `?string`) cannot take it and the request ends in a TypeError instead of the
 * "must not be blank" message. For such a property the empty value is the empty string.
 */
final class StringEmptyDataExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [TextType::class, TextareaType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $class = $form->getParent()?->getConfig()->getDataClass();
            if (null !== $event->getData() || !\is_string($class) || !\class_exists($class) || !\property_exists($class, $form->getName())) {
                return;
            }
            $type = new \ReflectionProperty($class, $form->getName())->getType();
            if ($type instanceof \ReflectionNamedType && !$type->allowsNull() && 'string' === $type->getName()) {
                $event->setData('');
            }
        });
    }
}
