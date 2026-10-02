<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Goku');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(WorkCrudController::class, 'Works', 'fa fa-book');
        yield MenuItem::linkTo(ChapterCrudController::class, 'Chapters', 'fa fa-file-lines');
    }
}
