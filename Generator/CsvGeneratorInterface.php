<?php
declare(strict_types=1);

namespace ChamberOrchestra\CmsBundle\Generator;

interface CsvGeneratorInterface
{
    public function generate(array $options): \SplFileInfo;
}