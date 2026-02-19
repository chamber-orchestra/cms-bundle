<?php

declare(strict_types=1);

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
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if (null === $file || !$file instanceof UploadedFile) {
            throw new UnexpectedValueException('Passed file must not be null and must be instance of '.UploadedFile::class.'.');
        }

        return new JsonResponse([
            'location' => $storage->resolveUri($storage->upload($file, $strategy)),
        ]);
    }
}
