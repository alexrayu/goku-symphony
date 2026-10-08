<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Entity\Work;
use App\Enum\PageStatus;
use App\Ingest\StorageKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Reads the local storage dir directly so a front server can take over the body: with
// SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER set, Nginx gets X-Accel-Redirect instead of the bytes.
// Unpublished chapters are visible to logged-in users only. Originals have no route.
// Browsers keep published media long; the edge keeps it an hour and then revalidates (a 304 from one
// PK query), so unpublishing takes images off Cloudflare within EDGE_MAX_AGE without a purge.
final class MediaController extends AbstractController
{
    private const EDGE_MAX_AGE = 3600;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(resolve:STORAGE_PATH)%')]
        private readonly string $storagePath,
    ) {
    }

    // Scrambled reading copy. Never changes: a re-upload creates new page ids.
    #[Route('/media/page/{id}.webp', name: 'media_page', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function page(int $id, Request $request): Response
    {
        $page = $this->em->createQuery(sprintf('SELECT p, c FROM %s p JOIN p.chapter c WHERE p.id = :id', Page::class))
            ->setParameter('id', $id)
            ->getOneOrNullResult();
        if (!$page instanceof Page || PageStatus::Ready !== $page->getStatus()) {
            throw new NotFoundHttpException();
        }

        return $this->serve($request, $page->getChapter()->isPublished(), StorageKeys::derivative($page), 'image/webp', immutable: true);
    }

    // Unscrambled link-preview image. Own path, so robots.txt can allow covers but not pages.
    #[Route('/media/cover/{id}.jpg', name: 'media_cover', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cover(int $id, Request $request): Response
    {
        $chapter = $this->em->find(Chapter::class, $id) ?? throw new NotFoundHttpException();

        // Replaced when the chapter's pages are re-uploaded under the same URL, so a day, not forever.
        return $this->serve($request, $chapter->isPublished(), StorageKeys::cover($chapter), 'image/jpeg', immutable: false);
    }

    // Unscrambled listing thumbnail; same lifetime as the cover.
    #[Route('/media/thumb/{id}.webp', name: 'media_thumb', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thumbnail(int $id, Request $request): Response
    {
        $chapter = $this->em->find(Chapter::class, $id) ?? throw new NotFoundHttpException();

        return $this->serve($request, $chapter->isPublished(), StorageKeys::thumbnail($chapter), 'image/webp', immutable: false);
    }

    // Chosen work cover. The version in the path changes on every upload, so the URL never goes stale.
    #[Route('/media/work/{id}/cover-{version}.jpg', name: 'media_work_cover', requirements: ['id' => '\d+', 'version' => '\d+'], methods: ['GET'])]
    public function workCover(int $id, Request $request): Response
    {
        $work = $this->em->find(Work::class, $id) ?? throw new NotFoundHttpException();

        return $this->serve($request, $this->isPublic($work), StorageKeys::workCover($work), 'image/jpeg', immutable: true);
    }

    #[Route('/media/work/{id}/thumb-{version}.webp', name: 'media_work_thumb', requirements: ['id' => '\d+', 'version' => '\d+'], methods: ['GET'])]
    public function workThumbnail(int $id, Request $request): Response
    {
        $work = $this->em->find(Work::class, $id) ?? throw new NotFoundHttpException();

        return $this->serve($request, $this->isPublic($work), StorageKeys::workThumbnail($work), 'image/webp', immutable: true);
    }

    #[Route('/media/site/logo-{version}.webp', name: 'media_logo', requirements: ['version' => '\d+'], methods: ['GET'])]
    public function logo(Request $request): Response
    {
        return $this->serve($request, true, StorageKeys::logo(), 'image/webp', immutable: true);
    }

    // A work is public once one of its chapters is.
    private function isPublic(Work $work): bool
    {
        return null !== $this->em->createQuery(sprintf('SELECT c.id FROM %s c WHERE c.work = :work AND c.published = true', Chapter::class))
            ->setParameter('work', $work)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    // Unpublished media is visible to logged-in users only, never cached.
    private function serve(Request $request, bool $published, string $key, string $contentType, bool $immutable): Response
    {
        $file = $this->storagePath.'/'.$key;
        if ((!$published && null === $this->getUser()) || !is_file($file)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($file, headers: ['Content-Type' => $contentType]);
        if ($published) {
            $response->setPublic()->setMaxAge($immutable ? 31536000 : 86400)->setSharedMaxAge(self::EDGE_MAX_AGE);
            if ($immutable) {
                $response->setImmutable();
            }
            // BinaryFileResponse sets Last-Modified from the file; a matching revalidation gets an empty 304.
            $response->isNotModified($request);
        } else {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
