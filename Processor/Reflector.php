<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Processor\Reflection\RuntimeReflectionClass;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Proxy;

class Reflector
{
    private static array $cached = [];

    public function create(object $instance): RuntimeReflectionClass
    {
        if ($instance instanceof Proxy) {
            if (!$instance->__isInitialized()) {
                $instance->__load();
            }
        }

        return self::$cached[$class = ClassUtils::getClass($instance)] ??= new RuntimeReflectionClass($class);
    }
}