<?php

declare(strict_types=1);

namespace App\Controller;

use App\Site\SiteSettingsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Pages about the artist rather than a work.
final class SiteController extends AbstractController
{
    public function __construct(private readonly SiteSettingsProvider $settings)
    {
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        if (!$this->settings->get()->hasAbout()) {
            throw new NotFoundHttpException();
        }

        return $this->render('public/about.html.twig');
    }

    // The default mark in the artist's accent colour; a page links the logo instead when there is one.
    #[Route('/favicon.svg', name: 'favicon', methods: ['GET'], format: 'svg')]
    public function favicon(): Response
    {
        return $this->render('theme/favicon.svg.twig', [], new Response(headers: ['Content-Type' => 'image/svg+xml']));
    }
}
