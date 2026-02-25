<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Regex;

/**
 * Build according to.
 *
 * @see http://www.unicode.org/reports/tr46/
 *
 * @source http://unicode.org/Public/idna/latest/IdnaMappingTable.txt
 */
final readonly class UrlRegex
{
    public const PATH_CHARS = "a-zA-Z0-9\-._~$!&'()*+,;=:@\\\[\]%";

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     */
    public static function getInvalidPathPartChars(): string
    {
        /** @var string|null $regex */
        static $regex;
        if (null !== $regex) {
            return $regex;
        }

        $regex = '[^'.self::PATH_CHARS.']';

        return $regex;
    }

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     */
    public static function getInvalidPathChars(): string
    {
        /** @var string|null $regex */
        static $regex;
        if (null !== $regex) {
            return $regex;
        }

        $regex = '[^'.self::PATH_CHARS.'\/]';

        return $regex;
    }

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.4
     */
    /** @phpstan-ignore method.unused */
    private static function getQueryPattern(): string
    {
        /** @var string|null $regex */
        static $regex;

        if (null !== $regex) {
            return $regex;
        }

        $regex =
            '(?:\?'
            .'(?:'
            // '\' '[' ']' unescaped are not allowed, but commonly used
            .'[a-zA-Z0-9\-._~!$&\'()*+,;=:@\\\[\]\/\?]'
            .'|'
            .'%[0-9A-Fa-f]{2}'
            .')*'
            .')*';

        return $regex;
    }

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.5
     */
    /** @phpstan-ignore method.unused */
    private static function getFragmentPattern(): string
    {
        /** @var string|null $regex */
        static $regex;

        if (null !== $regex) {
            return $regex;
        }

        $regex =
            '(?:\#'
            .'(?:'
            // '\' '[' ']' unescaped are not allowed, but commonly used
            .'[a-zA-Z0-9\-._~!$&\'()*+,;=:@\\\[\]\/?]'
            .'|'
            .'%[0-9A-Fa-f]{2}'
            .')*'
            .')*';

        return $regex;
    }
}
