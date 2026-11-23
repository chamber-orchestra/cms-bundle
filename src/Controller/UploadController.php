<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Controller;

use ChamberOrchestra\FileBundle\NamingStrategy\HashingNamingStrategy;
use ChamberOrchestra\FileBundle\Storage\StorageResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The upload of media for the editor (`wysiwyg`): the file goes to the default storage of the file bundle under a hashed name, the answer is `{"location": "<uri>"}` (or
 * `{"error": "<text>"}` with 422). The application imports the controller where it wants it (`/abc` is for administrators), under the route name
 * `cms_upload_image`; `WysiwygType` offers the upload when that route exists.
 */
#[Route('/upload', name: 'cms_upload_')]
final class UploadController extends AbstractController
{
    /** What may be uploaded: pictures a browser shows (an SVG can carry a script, so it is not one of them). */
    public const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];

    /** Documents (not executables, not scripts, not markup a browser would run). */
    public const array FILE_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/plain',
        'text/csv',
        'application/zip',
    ];

    public const array FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'csv', 'zip'];

    public const string MAX_SIZE = '10M';

    #[Route('/image', name: 'image', methods: ['POST'])]
    public function image(Request $request, StorageResolver $storages, HashingNamingStrategy $strategy, ValidatorInterface $validator): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file.'], 422);
        }

        $violations = $validator->validate($file, new Image(maxSize: self::MAX_SIZE, mimeTypes: self::IMAGE_TYPES, detectCorrupted: true));
        if (\count($violations) > 0) {
            return new JsonResponse(['error' => (string) $violations->get(0)->getMessage()], 422);
        }

        $storage = $storages->get('default');

        return new JsonResponse(['location' => $storage->resolveUri($storage->upload($file, $strategy, 'editor'))]);
    }

    /**
     * A plain file (a price list, a datasheet): the same storage and answer; the link to it is what the editor inserts.
     */
    #[Route('/file', name: 'file', methods: ['POST'])]
    public function document(Request $request, StorageResolver $storages, HashingNamingStrategy $strategy, ValidatorInterface $validator): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file.'], 422);
        }

        if (!\in_array(\strtolower($file->getClientOriginalExtension()), self::FILE_EXTENSIONS, true)) {
            return new JsonResponse(['error' => 'This kind of file is not accepted.'], 422);
        }

        $violations = $validator->validate($file, new File(maxSize: self::MAX_SIZE, mimeTypes: self::FILE_TYPES));
        if (\count($violations) > 0) {
            return new JsonResponse(['error' => (string) $violations->get(0)->getMessage()], 422);
        }

        $storage = $storages->get('default');

        return new JsonResponse(['location' => $storage->resolveUri($storage->upload($file, $strategy, 'files')), 'name' => $file->getClientOriginalName()]);
    }
}
