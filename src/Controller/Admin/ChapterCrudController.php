<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Chapter;
use App\Entity\Work;
use App\Enum\ReadingDirection;
use App\Ingest\ArchiveIngestor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
        return $crud
            ->setEntityLabelInSingular('Chapter')
            ->setEntityLabelInPlural('Chapters')
            ->setDefaultSort(['work' => 'ASC', 'number' => 'ASC']);
    }

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    // Chapter requires a Work; preselect the newest one (the choice field only accepts managed entities).
    public function createEntity(string $entityFqcn): Chapter
    {
        $work = $this->em->getRepository(Work::class)->findOneBy([], ['id' => 'DESC'])
            ?? throw new ConflictHttpException('Create a Work before adding chapters.');

        return new Chapter($work, '1');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('work')->setFormTypeOption('constraints', [new NotBlank()]);
        // DECIMAL arrives as a string; sprintf formatting avoids the int|float-only Intl formatter.
        // Lists show the public label ("12", "12.5"); the form keeps one decimal.
        yield NumberField::new('number')->setNumDecimals(1)->setStoredAsString()->setNumberFormat('%.1f')
            ->formatValue(static fn (mixed $value, Chapter $chapter): string => $chapter->getNumberLabel());
        yield TextField::new('title');
        yield ChoiceField::new('direction')->setChoices(ReadingDirection::cases());
        yield BooleanField::new('published');
        yield CollectionField::new('pages')->onlyOnDetail()->setTemplatePath('admin/chapter/pages.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('uploadPages', 'Upload pages', 'fa fa-file-zipper')->linkToCrudAction('uploadPages');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Crud::PAGE_DETAIL, $upload);
    }

    #[AdminRoute('/{id}/upload', 'upload', options: ['methods' => ['GET', 'POST']])]
    public function uploadPages(
        #[MapEntity] Chapter $chapter,
        Request $request,
        ArchiveIngestor $ingestor,
        AdminUrlGenerator $urlGenerator,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                // PHP drops the whole body, token included, when it exceeds post_max_size.
                if (0 === $request->request->count() && 0 === $request->files->count()) {
                    throw new \InvalidArgumentException('The upload exceeds the server size limit.');
                }
                if (!$this->isCsrfTokenValid('chapter_upload', (string) $request->request->get('_token'))) {
                    throw new \InvalidArgumentException('Invalid CSRF token. Reload the page and try again.');
                }
                $file = $request->files->get('archive');
                if (!$file instanceof UploadedFile) {
                    throw new \InvalidArgumentException('Choose an archive to upload.');
                }

                $ingestor->accept($chapter, $file);
                $this->addFlash('success', 'Archive queued. Page status updates as the worker processes it.');

                return $this->redirect($urlGenerator->setController(self::class)->setAction(Action::DETAIL)
                    ->setEntityId($chapter->getId())->generateUrl());
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/chapter/upload.html.twig', ['chapter' => $chapter]);
    }
}
