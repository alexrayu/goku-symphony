<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Chapter;
use App\Enum\PageStatus;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;

// Removes a chapter's pages, or the whole chapter, with their stored files. Rows go first, files
// after the flush: a failure leaves orphan files, never rows pointing at missing files.
final class ChapterPages
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }

    /**
     * Empties the chapter so a new archive can be uploaded.
     *
     * @throws \InvalidArgumentException with a message safe to show the user
     */
    public function clear(Chapter $chapter): void
    {
        $this->assertIdle($chapter);
        $files = StorageKeys::pageFiles($chapter);

        $chapter->getPages()->clear();
        $this->em->flush();
        $this->deleteFiles($files['directories'], $files['files']);
    }

    /**
     * @throws \InvalidArgumentException with a message safe to show the user
     */
    public function deleteChapter(Chapter $chapter): void
    {
        $this->assertIdle($chapter);
        // Keys need the id, which is gone after the flush.
        $files = StorageKeys::pageFiles($chapter);
        $incoming = StorageKeys::incomingDirectory($chapter);

        $this->em->remove($chapter);
        $this->em->flush();
        $this->deleteFiles([...$files['directories'], $incoming], $files['files']);
    }

    // The worker would write files for a page that no longer exists.
    private function assertIdle(Chapter $chapter): void
    {
        foreach ($chapter->getPages() as $page) {
            if (\in_array($page->getStatus(), [PageStatus::Pending, PageStatus::Processing], true)) {
                throw new \InvalidArgumentException('Pages are still being processed. Try again when they are ready or failed.');
            }
        }
    }

    /**
     * @param list<string> $directories
     * @param list<string> $files
     */
    private function deleteFiles(array $directories, array $files): void
    {
        foreach ($directories as $directory) {
            $this->defaultStorage->deleteDirectory($directory);
        }
        foreach ($files as $file) {
            $this->defaultStorage->delete($file);
        }
    }
}
