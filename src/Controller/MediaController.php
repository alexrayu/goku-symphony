<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Enum\PageStatus;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Reads the local storage dir directly so a front server can take over the body: with
// SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER set, Nginx gets X-Accel-Redirect instead of the bytes.
// Unpublished chapters are visible to logged-in users only. Originals have no route.
final class MediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:STORAGE_PATH)%')]
        private readonly string $storagePath,
    ) {
    }

    // Scrambled reading copy. Never changes: a re-upload creates new page ids.
    #[Route('/media/page/{id}.webp', name: 'media_page', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function page(int $id): BinaryFileResponse
    {
        $page = $this->em->createQuery(sprintf('SELECT p, c FROM %s p JOIN p.chapter c WHERE p.id = :id', Page::class))
            ->setParameter('id', $id)
            ->getOneOrNullResult();
        if (!$page instanceof Page || PageStatus::Ready !== $page->getStatus()) {
            throw new NotFoundHttpException();
        }

        return $this->serve($page->getChapter(), StorageKeys::derivative($page), 'image/webp', immutable: true);
    }

    // Unscrambled link-preview image. Own path, so robots.txt can allow covers but not pages.
    #[Route('/media/cover/{id}.jpg', name: 'media_cover', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cover(int $id): BinaryFileResponse
    {
        $chapter = $this->em->find(Chapter::class, $id) ?? throw new NotFoundHttpException();

        // Replaced when the chapter's pages are re-uploaded under the same URL, so a day, not forever.
        return $this->serve($chapter, StorageKeys::cover($chapter), 'image/jpeg', immutable: false);
    }

    // Unscrambled listing thumbnail; same lifetime as the cover.
    #[Route('/media/thumb/{id}.webp', name: 'media_thumb', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thumbnail(int $id): BinaryFileResponse
    {
        $chapter = $this->em->find(Chapter::class, $id) ?? throw new NotFoundHttpException();

        return $this->serve($chapter, StorageKeys::thumbnail($chapter), 'image/webp', immutable: false);
    }

    private function serve(Chapter $chapter, string $key, string $contentType, bool $immutable): BinaryFileResponse
    {
        $file = $this->storagePath.'/'.$key;
        if ((!$chapter->isPublished() && null === $this->getUser()) || !is_file($file)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($file, headers: ['Content-Type' => $contentType]);
        if ($chapter->isPublished()) {
            $response->setPublic()->setMaxAge($immutable ? 31536000 : 86400);
            if ($immutable) {
                $response->setImmutable();
            }
        } else {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
