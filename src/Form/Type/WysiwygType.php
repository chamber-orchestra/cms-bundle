<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A rich text field: a textarea that the `wysiwyg` Stimulus controller (Tiptap) turns into an editor. Without JavaScript it stays a textarea with the HTML.
 */
class WysiwygType extends AbstractType
{
    public function __construct(private readonly ?UrlGeneratorInterface $urls = null)
    {
    }

    /**
     * The editor uploads pictures to the route `cms_upload_image` (UploadController) when the application has imported it; without it a picture is added by its URL.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        try {
            $attr = \is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];
            $attr['data-wysiwyg-upload-url'] = $this->urls?->generate('cms_upload_image');
            $attr['data-wysiwyg-file-url'] = $this->urls?->generate('cms_upload_file');
            $view->vars['attr'] = $attr;
        } catch (RouteNotFoundException) {
            // not imported: no upload
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'data-controller' => 'wysiwyg',
                'rows' => 12,
            ],
        ]);
    }

    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}
