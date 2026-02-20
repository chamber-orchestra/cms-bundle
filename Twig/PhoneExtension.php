<?php

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PhoneExtension extends AbstractExtension
{
    private const PATTERN = "(?<country>7)(?<code>[\d]{3})(?<number>(?<part_1>[\d]{3})(?<part_2>[\d]{2})(?<part_3>[\d]{2}))$";

    public function getFilters(): array
    {
        return [
            new TwigFilter('phone', [$this, 'phone'], ['is_safe' => ['html']]),
            new TwigFilter('phone_link', [$this, 'phoneLink'], ['is_safe' => ['html']]),
        ];
    }

    public function phone(string $phone = null, string $template = '<span>%country%&nbsp;(%code%)</span>&nbsp;%number%', array $attr = []): string
    {
        if (null === $phone || '' === $phone) {
            return '';
        }

        $parsed = $this->parse($phone);
        if (null === $parsed) {
            return '';
        }

        return $this->format($template, $parsed, $attr);
    }

    public function phoneLink(string $phone = null, array $attr = []): string
    {
        return $this->phone(
            $phone,
            '<a href="tel:%country%%code%%number%" %attr%>'
            .'<span>%country%&nbsp;(%code%)</span>&nbsp;%number%'
            .'</a>',
            $attr);
    }

    private function parse(string $string): ?array
    {
        $string = \preg_replace("/[^\d]/ui", '', $string);

        if (\preg_match('/'.self::PATTERN.'/ui', $string, $matches)) {
            return [
                'country' => \sprintf('+%s', $matches['country']),
                'code' => $matches['code'],
                'number' => \sprintf('%s-%s-%s', $matches['part_1'], $matches['part_2'], $matches['part_3']),
                'full' => \sprintf('+%s%s%s%s%s',
                    $matches['country'],
                    $matches['code'],
                    $matches['part_1'],
                    $matches['part_2'],
                    $matches['part_3']
                ),
            ];
        }

        return null;
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
