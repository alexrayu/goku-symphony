<?php

declare(strict_types=1);

namespace App\Ingest\Message;

final readonly class GenerateChapterCovers
{
    public function __construct(public int $chapterId)
    {
    }
}
