<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Service;

use Pentatrion\ViteBundle\Service\EntrypointsLookupCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What the browser can start loading before the page is built: the styles, the scripts and the fonts of the admin's entry (Vite). The links go out in the `103 Early Hints`
 * answer (see `AdminEarlyHints`) while the server still renders the page, so the first paint does not wait for the HTML to find them. Only for a build (the files of the
 * development server of Vite have other addresses).
 */
final class EarlyHints
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function __construct(
        private readonly ?EntrypointsLookupCollection $lookups = null,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir = '',
    ) {
    }

    /**
     * @return list<string> the values of Link headers (`</build/assets/x.css>; rel=preload; as=style`)
     */
    public function links(string $entry = 'cms'): array
    {
        if (isset($this->cache[$entry])) {
            return $this->cache[$entry];
        }
        if (null === $this->lookups) {
            return [];
        }
        $lookup = $this->lookups->getEntrypointsLookup();
        if (!$lookup->hasFile() || !$lookup->isBuild()) {
            return $this->cache[$entry] = [];
        }

        $links = [];
        foreach ($lookup->getCSSFiles($entry) as $css) {
            $links[] = \sprintf('<%s>; rel=preload; as=style', $css);
            foreach ($this->fontsOf($css) as $font) {
                $links[] = \sprintf('<%s>; rel=preload; as=font; type="font/woff2"; crossorigin', $font);
            }
        }
        foreach ([...$lookup->getJSFiles($entry), ...$lookup->getJavascriptDependencies($entry)] as $script) {
            $links[] = \sprintf('<%s>; rel=modulepreload', $script);
        }

        return $this->cache[$entry] = \array_values(\array_unique($links));
    }

    /**
     * The fonts a stylesheet loads (the first ones it names are what the first paint needs).
     *
     * @return list<string>
     */
    private function fontsOf(string $css): array
    {
        $file = $this->publicDir.$css;
        if ('' === $this->publicDir || !\is_file($file)) {
            return [];
        }
        $text = \file_get_contents($file);
        if (false === $text || 0 === \preg_match_all('~url\(["\']?([^)"\']+\.woff2)["\']?\)~', $text, $matches)) {
            return [];
        }

        $fonts = [];
        foreach ($matches[1] as $url) {
            if (\str_contains($url, 'brands') || \str_contains($url, 'v4compat')) {
                continue; // the icons of the admin are the solid and the regular ones: the others are loaded only when a page uses them
            }
            $fonts[] = \str_starts_with($url, '/') ? $url : \dirname($css).'/'.\ltrim($url, './');
        }

        return \array_slice(\array_values(\array_unique($fonts)), 0, 4);
    }
}
