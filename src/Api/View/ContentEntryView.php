<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Api\View;

use ChamberOrchestra\CmsBundle\Entity\ContentEntry;
use ChamberOrchestra\ViewBundle\View\View;

class ContentEntryView extends View
{
    public string $id;
    public string $contentTypeName;
    public int $sortOrder;
    /** @var array<string, mixed> */
    public array $data;

    public function __construct(ContentEntry $entry)
    {
        $schema = $entry->getContentType()->getSchema();

        $this->id = (string) $entry->getId();
        $this->contentTypeName = $entry->getContentType()->getName();
        $this->sortOrder = $entry->getSortOrder();
        $this->data = $this->normalizeData($entry->getData(), $schema);
    }

    /**
     * Cast sortOrder to int for collection items (stored as string by HTML forms).
     *
     * @param array<string, mixed>             $data
     * @param array<int, array<string, mixed>> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizeData(array $data, array $schema): array
    {
        foreach ($schema as $field) {
            if (($field['type'] ?? '') !== 'collection') {
                continue;
            }

            $name = \is_string($field['name'] ?? null) ? $field['name'] : '';

            if (!isset($data[$name]) || !\is_array($data[$name])) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $items */
            $items = $data[$name];

            foreach ($items as &$item) {
                $raw = $item['sortOrder'] ?? null;
                if (null !== $raw) {
                    $item['sortOrder'] = \is_int($raw) ? $raw : (\is_numeric($raw) ? (int) $raw : 0);
                }
            }
            unset($item);

            $data[$name] = $items;
        }

        return $data;
    }
}
