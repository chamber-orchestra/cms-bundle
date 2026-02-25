<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle;

use ChamberOrchestra\DoctrineClockBundle\Contracts\Entity\TimestampUpdateInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-require-extends AbstractController
 */
trait CachedControllerTrait // @phpstan-ignore trait.unused
{
    protected function createCachedResponse(TimestampUpdateInterface $object, int $lifetime = 300): Response
    {
        $response = new Response();
        $this->cacheResponse($response, null, $lifetime);

        $response->setEtag(\md5((string) $object->getUpdatedDatetime()->getTimestamp()));
        $response->setLastModified($object->getUpdatedDatetime());

        return $response;
    }

    protected function cacheResponse(Response $response, ?string $checksum = null, int $lifetime = 300): void
    {
        $response->setPublic();
        $response->setVary('Accept-Encoding');
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setSharedMaxAge($lifetime);

        if (null !== $checksum) {
            $response->setEtag($checksum);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderCached(Request $request, string $view, array $parameters = [], int $lifetime = 300): Response
    {
        $response = $this->render($view, $parameters);
        $this->cacheResponse($response, \md5((string) $response->getContent()), $lifetime);
        $response->isNotModified($request);

        return $response;
    }
}
