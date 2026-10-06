<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use App\Enum\PageStatus;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:STORAGE_PATH)%')]
        private readonly string $storagePath,
    ) {
    }

    // Derivatives only; originals have no route. Unpublished chapters are visible to logged-in users only.
    // Reads the local storage dir directly so a front server can take over the body: with
    // SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER set, Nginx gets X-Accel-Redirect instead of the bytes.
    #[Route('/media/page/{id}.webp', name: 'media_page', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function page(int $id): BinaryFileResponse
    {
        $page = $this->em->createQuery(sprintf('SELECT p, c FROM %s p JOIN p.chapter c WHERE p.id = :id', Page::class))
            ->setParameter('id', $id)
            ->getOneOrNullResult();
        if (!$page instanceof Page || PageStatus::Ready !== $page->getStatus()
            || (!$page->getChapter()->isPublished() && null === $this->getUser())) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($this->storagePath.'/'.StorageKeys::derivative($page), headers: ['Content-Type' => 'image/webp']);

        // A page's derivative never changes: re-upload creates new page ids.
        if ($page->getChapter()->isPublished()) {
            $response->setPublic()->setMaxAge(31536000)->setImmutable();
        } else {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
