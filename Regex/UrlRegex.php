<?php

declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Regex;

/**
 * Build according to.
 *
 * @see http://www.unicode.org/reports/tr46/
 * @source http://unicode.org/Public/idna/latest/IdnaMappingTable.txt
 */
readonly final class UrlRegex
{
    public const PATH_CHARS = "a-zA-Z0-9\-._~$!&'()*+,;=:@\\\[\]%";

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-3.3
     */
    public static function getInvalidPathPartChars(): string
    {
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
    private static function getQueryPattern(): string
    {
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
    private static function getFragmentPattern(): string
    {
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
