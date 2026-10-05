<?php

declare(strict_types=1);

namespace App\Ingest\Message;

final readonly class IngestArchive
{
    public function __construct(
        public int $chapterId,
        public string $archiveKey,
    ) {
    }
}
