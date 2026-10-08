<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        #[Autowire('%env(SITE_NAME)%')]
        private readonly string $siteName,
    ) {
    }

    // No dashboard content yet: land on the Work list instead of EasyAdmin's welcome page.
    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator->setController(WorkCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        // Same brand as the public site: its favicon, name, accent and dark scheme.
        return Dashboard::new()
            // Rendered raw by EasyAdmin: the name is escaped, the logo is markup.
            ->setTitle(sprintf('<img src="/favicon.svg" alt="" width="24" height="24" style="vertical-align: -5px; margin-right: .5rem">%s', htmlspecialchars($this->siteName)))
            ->setFaviconPath('/favicon.svg')
            ->setDefaultColorScheme('dark')
            ->setTheme(Theme::new()->primaryColor('#ff6a4d')->grays(GrayScale::ZINC));
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(WorkCrudController::class, 'Works', 'fa fa-book');
        yield MenuItem::linkTo(ChapterCrudController::class, 'Chapters', 'fa fa-file-lines');
        yield MenuItem::linkToUrl('View site', 'fa fa-arrow-up-right-from-square', $this->generateUrl('home'));
    }
}
