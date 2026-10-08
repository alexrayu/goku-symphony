<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\Page;
use App\Entity\Work;
use App\Enum\PageStatus;
use App\Enum\WorkType;
use App\Repository\ChapterRepository;
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

    // One card per work with a published chapter: its first chapter gives the cover, its last the "latest" line.
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        $shelf = [];
        foreach ($this->chapters->findAllPublishedWithWork() as $chapter) {
            $work = $chapter->getWork();
            $shelf[$work->getId()] ??= ['work' => $work, 'first' => $chapter, 'count' => 0];
            $shelf[$work->getId()]['latest'] = $chapter;
            ++$shelf[$work->getId()]['count'];
        }

        return $this->render('public/home.html.twig', ['shelf' => $shelf]);
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
            return $this->read($work, $published[0]->getNumber(), $published);
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

        return $this->read($work, $number, $this->chapters->findPublishedByWork($work));
    }

    /**
     * @param list<Chapter> $published the work's published chapters, for previous/next links
     */
    private function read(Work $work, string $number, array $published): Response
    {
        $chapter = $this->chapters->findPublishedForReader($work, $number) ?? throw new NotFoundHttpException();
        $index = array_search($chapter, $published, true);

        return $this->render('public/reader.html.twig', [
            'work' => $work,
            'chapter' => $chapter,
            'previous' => $published[$index - 1] ?? null,
            'next' => $published[$index + 1] ?? null,
            'pages' => $chapter->getPages()->filter(static fn (Page $p): bool => PageStatus::Ready === $p->getStatus()),
        ]);
    }
}
