<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Page;
use App\Entity\Work;
use App\Enum\PageStatus;
use App\Enum\WorkType;
use App\Repository\ChapterRepository;
use App\Repository\WorkRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Work URLs sit at the root. Negative priority lets fixed paths (/login, /admin, ...) match first;
// Work::$slug rejects slugs that would shadow them.
final class ReaderController extends AbstractController
{
    private const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public function __construct(private readonly ChapterRepository $chapters)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(WorkRepository $works): Response
    {
        return $this->render('public/home.html.twig', ['works' => $works->findPublished()]);
    }

    // Series: chapter list. Oneshot: its single chapter is read right here, no list.
    #[Route('/{slug}', name: 'work_show', requirements: ['slug' => self::SLUG], methods: ['GET'], priority: -10)]
    public function work(#[MapEntity(mapping: ['slug' => 'slug'])] Work $work): Response
    {
        $published = $this->chapters->findPublishedByWork($work);
        if ([] === $published) {
            throw new NotFoundHttpException();
        }

        if (WorkType::Oneshot === $work->getType()) {
            return $this->read($work, $published[0]->getNumber());
        }

        return $this->render('public/work.html.twig', ['work' => $work, 'chapters' => $published]);
    }

    #[Route('/{slug}/chapter-{number}', name: 'chapter_read', requirements: ['slug' => self::SLUG, 'number' => '\d{1,5}(\.\d)?'], methods: ['GET'], priority: -10)]
    public function chapter(#[MapEntity(mapping: ['slug' => 'slug'])] Work $work, string $number): Response
    {
        // A oneshot has one URL; its chapter URL would be duplicate content.
        if (WorkType::Oneshot === $work->getType()) {
            return $this->redirectToRoute('work_show', ['slug' => $work->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->read($work, $number);
    }

    private function read(Work $work, string $number): Response
    {
        $chapter = $this->chapters->findPublishedForReader($work, $number) ?? throw new NotFoundHttpException();

        return $this->render('public/reader.html.twig', [
            'work' => $work,
            'chapter' => $chapter,
            'pages' => $chapter->getPages()->filter(static fn (Page $p): bool => PageStatus::Ready === $p->getStatus()),
        ]);
    }
}
