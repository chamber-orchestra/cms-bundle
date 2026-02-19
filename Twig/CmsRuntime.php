<?php
declare(strict_types=1);

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
        if (static::$configured) {
            return;
        }

        static::$configured = true;
        static::$version = $buildId;
        static::$cacheLifetime = $cacheLifetime;
        static::$cacheNamespace = $namespace;
    }

    private function getAccessor(): PropertyAccessorInterface
    {
        if (null !== $this->accessor) {
            return $this->accessor;
        }

        $decorated = new PropertyAccessor(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
            PropertyAccessor::createCache(static::$cacheNamespace, static::$cacheLifetime, static::$version),
        );

        return $this->accessor = new ReflectionPropertyAccessor($decorated);
    }
}