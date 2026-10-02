<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Chapter;
use App\Entity\Work;
use App\Enum\ReadingDirection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractCrudController<Chapter>
 */
final class ChapterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Chapter::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['work' => 'ASC', 'number' => 'ASC']);
    }

    // Placeholder Work is replaced by the form's work field before persist.
    public function createEntity(string $entityFqcn): Chapter
    {
        return new Chapter(new Work('', ''), '');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('work')->setFormTypeOption('constraints', [new NotBlank()]);
        yield NumberField::new('number')->setNumDecimals(1);
        yield TextField::new('title');
        yield ChoiceField::new('direction')->setChoices(ReadingDirection::cases());
        yield BooleanField::new('published');
    }
}
