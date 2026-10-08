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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Work URLs sit at the root. Negative priority lets fixed paths (/login, /admin, ...) match first;
// Work::$slug rejects slugs that would shadow them.
final class ReaderController extends AbstractController
{
    private const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly RequestStack $requestStack,
    ) {
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
        $readable = $this->chapters->findReadableByWork($work, $this->previewsDrafts());
        if ([] === $readable) {
            throw new NotFoundHttpException();
        }

        if (WorkType::Oneshot === $work->getType()) {
            return $this->read($work, $readable[0]->getNumber(), $readable);
        }

        return $this->render('public/work.html.twig', ['work' => $work, 'chapters' => $readable]);
    }

    #[Route('/{slug}/chapter-{number}', name: 'chapter_read', requirements: ['slug' => self::SLUG, 'number' => '\d{1,5}(\.\d)?'], methods: ['GET'], priority: -10)]
    public function chapter(#[MapEntity(mapping: ['slug' => 'slug'])] Work $work, string $number): Response
    {
        // A oneshot has one URL; its chapter URL would be duplicate content.
        if (WorkType::Oneshot === $work->getType()) {
            return $this->redirectToRoute('work_show', ['slug' => $work->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->read($work, $number, $this->chapters->findReadableByWork($work, $this->previewsDrafts()));
    }

    /**
     * @param list<Chapter> $readable the work's readable chapters, for previous/next links
     */
    private function read(Work $work, string $number, array $readable): Response
    {
        $chapter = $this->chapters->findForReader($work, $number, $this->previewsDrafts()) ?? throw new NotFoundHttpException();
        $index = array_search($chapter, $readable, true);

        return $this->render('public/reader.html.twig', [
            'work' => $work,
            'chapter' => $chapter,
            'previous' => $readable[$index - 1] ?? null,
            'next' => $readable[$index + 1] ?? null,
            'pages' => $chapter->getPages()->filter(static fn (Page $p): bool => PageStatus::Ready === $p->getStatus()),
        ]);
    }

    // Logged-in users read drafts before release. Their responses are private (session),
    // so PublicPageCache never lets a draft into the shared cache. No session cookie means
    // anonymous: skip the user lookup, which would touch the session and make every page private.
    private function previewsDrafts(): bool
    {
        return true === $this->requestStack->getMainRequest()?->hasPreviousSession() && null !== $this->getUser();
    }
}
