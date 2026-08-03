<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * Validates, stores and returns the public URL path (relative to the host) for an uploaded file.
     *
     * @param string[] $allowedMimes
     */
    public function upload(
        UploadedFile $file,
        string $subdir,
        array $allowedMimes,
        int $maxBytes,
        string $prefix = '',
    ): string {
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new \RuntimeException('Unsupported file type', 400);
        }

        if ($file->getSize() > $maxBytes) {
            throw new \RuntimeException(sprintf('File must not exceed %d MB', intdiv($maxBytes, 1024 * 1024)), 400);
        }

        $ext = $file->guessExtension() ?? 'jpg';
        $filename = $prefix.bin2hex(random_bytes(12)).'.'.$ext;
        $dir = $this->projectDir.'/public/uploads'.('' !== $subdir ? '/'.$subdir : '');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file->move($dir, $filename);

        return '/uploads'.('' !== $subdir ? '/'.$subdir : '').'/'.$filename;
    }
}
