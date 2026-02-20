<?php declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle;

use ChamberOrchestra\DoctrineClockBundle\Contracts\Entity\TimestampUpdateInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin AbstractController
 */
trait CachedControllerTrait
{
    protected function createCachedResponse(TimestampUpdateInterface $object, int $lifetime = 300): Response
    {
        $response = new Response();
        $this->cacheResponse($response, null, $lifetime);

        $response->setEtag(\md5((string) $object->getUpdatedDatetime()->getTimestamp()));
        $response->setLastModified($object->getUpdatedDatetime());

        return $response;
    }

    protected function cacheResponse(Response $response, string $checksum = null, int $lifetime = 300): void
    {
        $response->setPublic();
        $response->setVary('Accept-Encoding');
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setSharedMaxAge($lifetime);

        if (null !== $checksum) {
            $response->setEtag($checksum);
        }
    }

    protected function renderCached(Request $request, string $view, array $parameters = [], int $lifetime = 300): Response
    {
        $response = $this->render($view, $parameters);
        $this->cacheResponse($response, \md5($response->getContent()), $lifetime);
        $response->isNotModified($request);

        return $response;
    }
}