<?php

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class EmailExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('email_link', [$this, 'emailLink'], ['is_safe' => ['html']]),
        ];
    }

    public function emailLink(string $email = null, array $attr = []): string
    {
        return $this->email(
            $email,
            '<a href="mailto:%email%" %attr%>'
            .'%email%'
            .'</a>',
            $attr);
    }

    public function email(?string $email, string $template, array $attr = []): string
    {
        if (null === $email || '' === $email) {
            return '';
        }

        return $this->format($template, ['email' => $email], $attr);
    }

    private function format(string $template, array $replace, array $attr): string
    {
        $atr = [];
        foreach ($attr as $key => $value) {
            $atr[] = $key.'="'.(true === $value ? $key : $value).'"';
        }
        $replace['attr'] = \implode(' ', $atr);

        $search = \array_keys($replace);
        $search = \array_map(function (string $value): string {
            return '%'.$value.'%';
        }, $search);

        return \str_replace($search, $replace, $template);
    }
}
