<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\FileBundle\Exception\UnexpectedValueException;
use ChamberOrchestra\FileBundle\NamingStrategy\HashingNamingStrategy;
use ChamberOrchestra\FileBundle\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/file-manager')]
class FileManagerController extends AbstractController
{
    #[Route('/image/upload', name: 'file_manager_image_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, StorageInterface $storage, HashingNamingStrategy $strategy): Response
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            throw new UnexpectedValueException('Passed file must not be null and must be instance of '.UploadedFile::class.'.');
        }

        return new JsonResponse([
            'location' => $storage->resolveUri($storage->upload($file, $strategy)),
        ]);
    }
}
