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

final class ReaderController extends AbstractController
{
    public function __construct(private readonly ChapterRepository $chapters)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(WorkRepository $works): Response
    {
        return $this->render('public/home.html.twig', ['works' => $works->findPublished()]);
    }

    // Series: chapter list. Oneshot: its single chapter is read right here, no list.
    #[Route('/w/{slug}', name: 'work_show', methods: ['GET'])]
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

    #[Route('/w/{slug}/{number}', name: 'chapter_read', requirements: ['number' => '\d{1,5}(\.\d)?'], methods: ['GET'])]
    public function chapter(#[MapEntity(mapping: ['slug' => 'slug'])] Work $work, string $number): Response
    {
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
