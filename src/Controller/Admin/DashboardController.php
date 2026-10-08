<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Media\PageImageUrlGenerator;
use App\Site\SiteSettingsProvider;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly SiteSettingsProvider $settings,
        private readonly PageImageUrlGenerator $imageUrls,
    ) {
    }

    // No dashboard content yet: land on the Work list instead of EasyAdmin's welcome page.
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(WorkCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        // Same brand as the public site (Site settings): its icon, name, accent and dark scheme.
        $site = $this->settings->get();
        $icon = $this->imageUrls->logo($site) ?? $this->generateUrl('favicon');

        return Dashboard::new()
            // Rendered raw by EasyAdmin: the name is escaped, the icon URL is ours.
            ->setTitle(sprintf('<img src="%s" alt="" height="24" style="vertical-align: -5px; margin-right: .5rem">%s', $icon, htmlspecialchars($site->getName())))
            ->setFaviconPath($icon)
            ->setDefaultColorScheme('dark')
            ->setTheme(Theme::new()->primaryColor($site->getAccent())->grays(GrayScale::ZINC));
    }

    // Optional fields left empty (e.g. a chapter without a title) show as blank cells, not a "Null" badge.
    public function configureCrud(): Crud
    {
        return parent::configureCrud()->overrideTemplate('label/null', 'admin/label/null.html.twig');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(WorkCrudController::class, 'Works', 'fa fa-book');
        yield MenuItem::linkTo(ChapterCrudController::class, 'Chapters', 'fa fa-file-lines');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'Site settings', 'fa fa-palette');
        yield MenuItem::linkToUrl('View site', 'fa fa-arrow-up-right-from-square', $this->generateUrl('home'));
    }
}
