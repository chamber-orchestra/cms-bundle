<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

class CoreExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('instanceof', [$this, 'instanceof']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('call', [$this, 'call']),
        ];
    }

    public function instanceof($value, string $type): bool
    {
        return ('null' === $type && null === $value)
               || (\function_exists($func = 'is_'.$type) && $func($value))
               || \is_a($value, $type, true);
    }

    public function call(callable $callable, ...$args)
    {
        return \call_user_func_array($callable, $args);
    }
}