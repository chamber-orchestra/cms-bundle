<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\EventSubscriber;

use ChamberOrchestra\CmsBundle\Service\EarlyHints;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends `103 Early Hints` for the pages of the admin (the routes of the bundle and of the application's admin are named `cms_*`): the browser starts loading the scripts, the
 * styles and the fonts of the entry while the page is still being built. An HTML page of a plain GET only (not a fragment, not JSON); a server that cannot send an informational
 * answer (`headers_send()`: FrankenPHP has it) is skipped silently.
 */
final readonly class AdminEarlyHints implements EventSubscriberInterface
{
    public function __construct(private EarlyHints $hints)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['send', -16]]; // after the router has found the route
    }

    public function send(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('GET') || $request->isXmlHttpRequest() || 'json' === $request->query->get('format')) {
            return;
        }
        $route = $request->attributes->get('_route');
        if (!\is_string($route) || !\str_starts_with($route, 'cms_') || !\function_exists('headers_send')) {
            return;
        }

        $links = $this->hints->links();
        if ([] === $links) {
            return;
        }
        $response = new Response();
        $response->headers->set('Link', $links);
        $response->sendHeaders(103);
    }
}
