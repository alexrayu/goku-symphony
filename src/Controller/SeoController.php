<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChapterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        return $this->render('seo/robots.txt.twig');
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(ChapterRepository $chapters): Response
    {
        return $this->render('seo/sitemap.xml.twig', ['groups' => $chapters->findPublishedGroupedByWork()]);
    }
}
