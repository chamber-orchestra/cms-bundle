<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class EmailExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('email_link', $this->emailLink(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string|bool> $attr
     */
    public function emailLink(?string $email = null, array $attr = []): string
    {
        return $this->email(
            $email,
            '<a href="mailto:%email%" %attr%>'
            .'%email%'
            .'</a>',
            $attr);
    }

    /**
     * @param array<string, string|bool> $attr
     */
    public function email(?string $email, string $template, array $attr = []): string
    {
        if (null === $email || '' === $email) {
            return '';
        }

        return $this->format($template, ['email' => $email], $attr);
    }

    /**
     * @param array<string, string>      $replace
     * @param array<string, string|bool> $attr
     */
    private function format(string $template, array $replace, array $attr): string
    {
        $atr = [];
        foreach ($attr as $key => $value) {
            $escaped = true === $value ? $key : \htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $atr[] = \htmlspecialchars($key, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'="'.$escaped.'"';
        }
        $replace['attr'] = \implode(' ', $atr);

        $search = \array_keys($replace);
        $search = \array_map(function (string $value): string {
            return '%'.$value.'%';
        }, $search);

        return \str_replace($search, \array_values($replace), $template);
    }
}
