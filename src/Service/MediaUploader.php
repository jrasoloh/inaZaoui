<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Handles storage of media files in a single, absolute, configured directory.
 *
 * The value returned/stored in Media::$path is the PUBLIC relative path
 * (e.g. "uploads/ab12....jpg") so it can be rendered with Twig's asset().
 * Filesystem operations always use the absolute directory + basename, so they
 * no longer depend on the PHP process current working directory.
 */
class MediaUploader
{
    public function __construct(
        private readonly string $uploadsDir,
        private readonly string $publicPrefix = 'uploads',
    ) {
    }

    /**
     * Moves the uploaded file into the uploads directory.
     *
     * @return string the public relative path to store (e.g. "uploads/<name>.<ext>")
     */
    public function upload(UploadedFile $file): string
    {
        $filename = md5(uniqid('', true)).'.'.$file->guessExtension();
        $file->move($this->uploadsDir, $filename);

        return $this->publicPrefix.'/'.$filename;
    }

    /**
     * Removes a stored file given its public relative path. Safe if missing.
     */
    public function remove(?string $publicPath): void
    {
        if (null === $publicPath || '' === $publicPath) {
            return;
        }

        $absolute = $this->uploadsDir.'/'.basename($publicPath);

        if (is_file($absolute)) {
            try {
                unlink($absolute);
            } catch (FileException) {
                // Ignore: the DB row is already removed; a missing file must not break the flow.
            }
        }
    }
}

