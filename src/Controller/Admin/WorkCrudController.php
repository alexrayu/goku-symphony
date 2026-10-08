<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Work;
use App\Enum\WorkType;
use App\Ingest\CustomImages;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;

/**
 * @extends AbstractCrudController<Work>
 */
final class WorkCrudController extends AbstractCrudController
{
    public function __construct(private readonly CustomImages $customImages)
    {
    }

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
        yield Field::new('coverUpload', 'Cover')->setFormType(FileType::class)->setFormTypeOptions(['required' => false])
            ->onlyOnForms()
            ->setHelp('Optional. PNG, JPEG or WebP, cropped to a 5:7 card and a 1200x630 link preview. Without one, the first page of the first chapter is used.');
        if (Crud::PAGE_EDIT === $pageName && null !== $this->getContext()?->getEntity()->getInstance()?->getCoverVersion()) {
            yield BooleanField::new('removeCover', 'Remove the chosen cover')->renderAsSwitch(false)->onlyOnForms();
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        $this->applyCover($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $this->applyCover($entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->customImages->deleteWork($entityInstance);
    }

    // After the flush: the work needs its id for the storage keys.
    private function applyCover(Work $work): void
    {
        try {
            if (null !== $upload = $work->getCoverUpload()) {
                $this->customImages->storeWorkCover($work, $upload);
            } elseif ($work->isRemoveCover()) {
                $this->customImages->deleteWorkCover($work);
            }
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
    }
}
