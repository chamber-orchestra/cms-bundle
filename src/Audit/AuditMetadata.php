<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Audit;

use ChamberOrchestra\CmsBundle\Audit\Attribute\Auditable;
use ChamberOrchestra\CmsBundle\Audit\Attribute\AuditIgnore;
use ChamberOrchestra\CmsBundle\Audit\Attribute\AuditTransform;
use Doctrine\Common\Util\ClassUtils;

/**
 * What the attributes say about a class of entities: is it journalled (`#[Auditable]` on it or on one of its parents), which actions, which properties are ignored or
 * written in another form. Read by reflection once per class and kept for the process.
 */
final class AuditMetadata
{
    /** @var array<class-string, array{actions: list<string>, ignored: array<string, true>, transforms: array<string, string>}|null> */
    private array $classes = [];

    /**
     * @param object|class-string $entity
     *
     * @return array{actions: list<string>, ignored: array<string, true>, transforms: array<string, string>}|null null: not journalled
     */
    public function of(object|string $entity): ?array
    {
        $class = \is_object($entity) ? ClassUtils::getClass($entity) : $entity;
        if (\array_key_exists($class, $this->classes)) {
            return $this->classes[$class];
        }

        return $this->classes[$class] = $this->read($class);
    }

    /**
     * @param class-string $class
     *
     * @return array{actions: list<string>, ignored: array<string, true>, transforms: array<string, string>}|null
     */
    private function read(string $class): ?array
    {
        $actions = null;
        $ignored = [];
        $transforms = [];
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            if (null === $actions) {
                $found = $reflection->getAttributes(Auditable::class);
                if ([] !== $found) {
                    $actions = $found[0]->newInstance()->actions;
                }
            }
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue; // read once, in the class that declares it
                }
                $name = $property->getName();
                if ([] !== $property->getAttributes(AuditIgnore::class)) {
                    $ignored[$name] = true;
                }
                $transform = $property->getAttributes(AuditTransform::class);
                if ([] !== $transform) {
                    $transforms[$name] = $transform[0]->newInstance()->transformation;
                }
            }
        }

        return null === $actions ? null : ['actions' => $actions, 'ignored' => $ignored, 'transforms' => $transforms];
    }
}
