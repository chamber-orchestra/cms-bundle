<?php

namespace ChamberOrchestra\CmsBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @mixin AbstractCrudController
 */
trait OriginPathTrait
{
    protected function saveOriginPath(): void
    {
        $request = $this->container->get('request_stack')->getMainRequest();
        $this->setOriginPath($request, $request->headers->get('referer', null));
    }

    protected function getOriginPath(string $route, array $params = []): string
    {
        $request = $this->container->get('request_stack')->getMainRequest();
        if ($origin = $request->getSession()->get('_cms.origin_path')) {
            $this->setOriginPath($request, null);

            return $origin;
        }

        return $this->generateUrl($route, $params);
    }

    protected function getBackPath(string $route, array $params = []): string
    {
        $request = $this->container->get('request_stack')->getMainRequest();
        $referer = $request->headers->get('referer');

        if (null !== $referer && \parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return $this->generateUrl($route, $params);
    }

    protected function redirectBackToOrigin(string $route, array $params = []): RedirectResponse
    {
        return $this->redirect($this->getOriginPath($route, $params));
    }

    protected function redirectBack(string $route, array $params = []): RedirectResponse
    {
        return $this->redirect($this->getBackPath($route, $params));
    }

    private function setOriginPath(Request $request, ?string $path): void
    {
        $request->getSession()->set('_cms.origin_path', $path);
    }
}
