<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Work;
use App\Enum\WorkType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Work>
 */
final class WorkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Work::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Work')
            ->setEntityLabelInPlural('Works')
            ->setDefaultSort(['title' => 'ASC']);
    }

    // EasyAdmin instantiates with no arguments; Work requires title and slug.
    public function createEntity(string $entityFqcn): Work
    {
        return new Work('', '');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title');
        yield SlugField::new('slug')->setTargetFieldName('title');
        yield ChoiceField::new('type')->setChoices(WorkType::cases());
        yield TextareaField::new('description')->hideOnIndex();
    }
}
