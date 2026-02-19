<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Generator;

use ChamberOrchestra\CmsBundle\EntityRepository\EntityRepositoryWrapper;
use ChamberOrchestra\CmsBundle\PropertyAccessor\ReflectionPropertyAccessor;
use ChamberOrchestra\FileBundle\Model\File;

class CsvGenerator implements CsvGeneratorInterface
{
    use \EntityManagerAwareTrait;
    use \TranslatorAwareTrait;

    protected string $delimiter = ';';

    public function __construct(private readonly ReflectionPropertyAccessor $accessor)
    {
    }

    public function generate(array $options): \SplFileInfo
    {
        $file = \tempnam(\sys_get_temp_dir(), 'csv-generate-');
        $handle = $this->open($file);
        $fields = empty($options['index']['export']) ? \array_keys($options['index']['fields']) : $options['index']['export'];

        \fputcsv($handle, $this->getHeaders($fields, $options), $this->delimiter);

        foreach ($this->buildIndex($options) as $entity) {
            \fputcsv($handle, $this->prepareData($fields, $entity), $this->delimiter);
        }

        \fclose($handle);

        return new \SplFileInfo($file);
    }

    protected function buildIndex(array $options): iterable
    {
        $er = $this->em->getRepository($options['class']);

        return (new EntityRepositoryWrapper($er))->filterBy(
            $er->createQueryBuilder('r'),
            $options['parent'] ? ['parent' => $options['parent']] : [],
            $options['index']['filter_mapping'],
            $options['index']['order_by'],
            $options['index']['order_by_mapping'],
        );
    }

    protected function getHeaders(array $fields, array $options): array
    {
        return \array_map(fn(string $field) => $this->label($field, $options['label_format']), $fields);
    }

    protected function prepareData(array $fields, object $entity): array
    {
        return \array_map(fn(string $field) => $this->format($this->accessor->getValue($entity, $field)), $fields);
    }

    private function open(string $name)
    {
        $handle = \fopen($name, 'w');
        $bom = \chr(0xEF).\chr(0xBB).\chr(0xBF);
        \fputs($handle, $bom);

        return $handle;
    }

    private function label(string $field, string $labelFormat): string
    {
        return $this->translator->trans(\str_replace("%name%", $field, $labelFormat), [], 'cms');
    }

    private function format(mixed $value): string
    {
        if ($value instanceof File) {
            return $value->getUri();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (\is_array($value) || $value instanceof \Traversable) {
            return \implode(',', \is_array($value) ? $value : \iterator_to_array($value));
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}