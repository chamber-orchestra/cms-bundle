<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Processor;

use ChamberOrchestra\CmsBundle\Processor\Reflection\RuntimeReflectionClass;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Proxy;

class Reflector
{
    /** @var array<string, RuntimeReflectionClass<object>> */
    private static array $cached = [];

    /**
     * @return RuntimeReflectionClass<object>
     */
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
