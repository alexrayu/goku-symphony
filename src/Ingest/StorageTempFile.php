<?php

declare(strict_types=1);

namespace App\Ingest;

use League\Flysystem\FilesystemOperator;

// ZipArchive and the vips CLI need a local path; storage may not be local, so copy by stream.
final class StorageTempFile
{
    public function __construct(private readonly FilesystemOperator $defaultStorage)
    {
    }

    /**
     * @return string local path; the caller unlinks it
     */
    public function download(string $key): string
    {
        $path = tempnam(sys_get_temp_dir(), 'goku');
        $out = false === $path ? false : fopen($path, 'wb');
        if (false === $path || false === $out) {
            throw new \RuntimeException('Cannot create a temp file.');
        }

        $in = $this->defaultStorage->readStream($key);
        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }

        return $path;
    }
}
