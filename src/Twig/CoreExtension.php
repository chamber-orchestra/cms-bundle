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
use Twig\TwigFunction;
use Twig\TwigTest;

class CoreExtension extends AbstractExtension
{
    /**
     * @return list<TwigTest>
     */
    public function getTests(): array
    {
        return [
            new TwigTest('instanceof', $this->instanceof(...)),
        ];
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('call', $this->call(...)),
        ];
    }

    public function instanceof(mixed $value, string $type): bool
    {
        return ('null' === $type && null === $value)
               || (\function_exists($func = 'is_'.$type) && $func($value))
               || ((\is_object($value) || \is_string($value)) && \is_a($value, $type, true));
    }

    public function call(callable $callable, mixed ...$args): mixed
    {
        return \call_user_func_array($callable, $args);
    }
}
