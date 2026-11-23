<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Twig;

use Symfony\Component\HttpFoundation\File\File;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `cms_file_info(file)`: what the form shows under an uploaded file: the date it was uploaded, the MIME type and the size. Null when there is no file on the disk
 * (a file in a remote storage, or one that is gone): the form then shows nothing.
 */
final class FileInfoExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('cms_file_info', $this->info(...))];
    }

    /**
     * @return array{date: \DateTimeImmutable, mime: string, size: string}|null
     */
    public function info(?File $file): ?array
    {
        if (null === $file || !\is_file($path = $file->getPathname())) {
            return null;
        }
        $time = \filemtime($path);
        $size = \filesize($path);
        if (false === $time || false === $size) {
            return null;
        }

        return [
            'date' => new \DateTimeImmutable()->setTimestamp($time),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => self::size($size),
        ];
    }

    private static function size(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes.' B',
            $bytes < 1048576 => \number_format($bytes / 1024, 1, '.', ' ').' KB',
            default => \number_format($bytes / 1048576, 1, '.', ' ').' MB',
        };
    }
}
