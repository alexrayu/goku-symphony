<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Page;
use App\Enum\PageStatus;
use App\Image\ImageProcessor;
use App\Ingest\Message\GenerateDerivative;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class DerivativeGenerator
{
    private const MAX_WIDTH = 1200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator $defaultStorage,
        private readonly ImageProcessor $images,
        private readonly StorageTempFile $tempFile,
    ) {
    }

    #[AsMessageHandler]
    public function generate(GenerateDerivative $message): void
    {
        $page = $this->em->find(Page::class, $message->pageId);
        if (null === $page) {
            return;
        }
        $page->setStatus(PageStatus::Processing);
        $this->em->flush();

        $source = $this->tempFile->download($page->getOriginalKey());
        $target = $source.'.webp';
        try {
            [$width, $height] = $this->images->toWebp($source, $target, self::MAX_WIDTH);
            $stream = fopen($target, 'rb');
            if (false === $stream) {
                throw new \RuntimeException('Cannot read the generated derivative.');
            }
            try {
                $this->defaultStorage->writeStream(StorageKeys::derivative($page), $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            foreach ([$source, $target] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Derivative dimensions: what the reader displays and reserves space for.
        $page->setDimensions($width, $height)->setStatus(PageStatus::Ready);
        $this->em->flush();
    }

    // Retries exhausted: make the failure visible on the page instead of leaving it "processing".
    #[AsEventListener]
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof GenerateDerivative) {
            return;
        }

        $page = $this->em->find(Page::class, $message->pageId);
        if (null !== $page) {
            $page->setStatus(PageStatus::Failed);
            $this->em->flush();
        }
    }
}
