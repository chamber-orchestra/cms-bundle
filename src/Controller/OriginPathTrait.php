<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $request = $this->getRequest();
        $this->setOriginPath($request, $request->headers->get('referer', null));
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function getOriginPath(string $route, array $params = []): string
    {
        $request = $this->getRequest();
        /** @var string|null $origin */
        $origin = $request->getSession()->get('_cms.origin_path');
        if ($origin) {
            $this->setOriginPath($request, null);

            return $origin;
        }

        return $this->generateUrl($route, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function getBackPath(string $route, array $params = []): string
    {
        $request = $this->getRequest();
        $referer = $request->headers->get('referer');

        if (null !== $referer
            && \parse_url($referer, \PHP_URL_HOST) === $request->getHost()
            && \parse_url($referer, \PHP_URL_SCHEME) === $request->getScheme()
        ) {
            return $referer;
        }

        return $this->generateUrl($route, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function redirectBackToOrigin(string $route, array $params = []): RedirectResponse
    {
        return $this->redirect($this->getOriginPath($route, $params));
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function redirectBack(string $route, array $params = []): RedirectResponse
    {
        return $this->redirect($this->getBackPath($route, $params));
    }

    private function setOriginPath(Request $request, ?string $path): void
    {
        $request->getSession()->set('_cms.origin_path', $path);
    }
}
