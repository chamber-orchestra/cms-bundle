<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Extension;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Quick creation of what a select lists: `'quick_create' => 'cms_vendor_create'` (the route of the create action of the CRUD controller that manages the listed
 * entity) puts a "+" beside the select. It opens the create form of that controller in a dialog; the saved entity is added to the select and chosen. For
 * reference books (an id, a name and a few fields). The route is only named in the form type, the bundle does not know any.
 */
final class QuickCreateExtension extends AbstractTypeExtension
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [EntityType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('quick_create', null);
        $resolver->setAllowedTypes('quick_create', ['null', 'string']);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (null === $options['quick_create'] || $options['disabled']) {
            return;
        }

        $attr = \is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];
        $attr['data-quick-create-url'] = $this->urls->generate(\is_string($options['quick_create']) ? $options['quick_create'] : '', ['quick' => 1]);
        $view->vars['attr'] = $attr;
    }
}
