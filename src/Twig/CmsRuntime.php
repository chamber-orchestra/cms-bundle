<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Twig;

use ChamberOrchestra\CmsBundle\PropertyAccessor\ReflectionPropertyAccessor;
use Doctrine\Persistence\Proxy;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Twig\Extension\RuntimeExtensionInterface;

class CmsRuntime implements RuntimeExtensionInterface
{
    private ?PropertyAccessorInterface $accessor = null;
    private static bool $configured = false;
    private static string $cacheNamespace = 'cms_runtime';
    private static int $cacheLifetime = 0;
    private static string $version = '';

    /** @phpstan-ignore constructor.unusedParameter */
    public function __construct(?CacheItemPoolInterface $cacheItemPool = null)
    {
    }

    public function read(object $object, string $attribute): mixed
    {
        if ($object instanceof Proxy && !$object->__isInitialized()) {
            $object->__load();
        }

        $accessor = $this->getAccessor();

        if (!$accessor->isReadable($object, $attribute)) {
            return null;
        }

        return $accessor->getValue($object, $attribute);
    }

    public static function configure(string $buildId, int $cacheLifetime = 0, string $namespace = 'view'): void
    {
        if (self::$configured) {
            return;
        }

        self::$configured = true;
        self::$version = $buildId;
        self::$cacheLifetime = $cacheLifetime;
        self::$cacheNamespace = $namespace;
    }

    /**
     * @internal for testing only
     */
    public static function reset(): void
    {
        self::$configured = false;
        self::$version = '';
        self::$cacheLifetime = 0;
        self::$cacheNamespace = 'cms_runtime';
    }

    private function getAccessor(): PropertyAccessorInterface
    {
        if (null !== $this->accessor) {
            return $this->accessor;
        }

        $decorated = new PropertyAccessor(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
            PropertyAccessor::createCache(self::$cacheNamespace, self::$cacheLifetime, self::$version),
        );

        return $this->accessor = new ReflectionPropertyAccessor($decorated);
    }
}
