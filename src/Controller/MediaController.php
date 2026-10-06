<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use App\Enum\PageStatus;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }

    // Derivatives only; originals have no route. Unpublished chapters are visible to logged-in users only.
    #[Route('/media/page/{id}.webp', name: 'media_page', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function page(int $id): StreamedResponse
    {
        $page = $this->em->createQuery(sprintf('SELECT p, c FROM %s p JOIN p.chapter c WHERE p.id = :id', Page::class))
            ->setParameter('id', $id)
            ->getOneOrNullResult();
        if (!$page instanceof Page || PageStatus::Ready !== $page->getStatus()
            || (!$page->getChapter()->isPublished() && null === $this->getUser())) {
            throw new NotFoundHttpException();
        }

        $stream = $this->defaultStorage->readStream(StorageKeys::derivative($page));
        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', 'image/webp');

        // A page's derivative never changes: re-upload creates new page ids.
        if ($page->getChapter()->isPublished()) {
            $response->setPublic()->setMaxAge(31536000)->setImmutable();
        } else {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
