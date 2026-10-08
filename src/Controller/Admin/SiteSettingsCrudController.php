<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSettings;
use App\Ingest\CustomImages;
use App\Site\SiteSettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single settings row, edit page only. The menu lands on index, which creates the row from
 * the env defaults on first use and redirects to its form.
 *
 * @extends AbstractCrudController<SiteSettings>
 */
final class SiteSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteSettingsProvider $settings,
        private readonly CustomImages $customImages,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Site settings')->setPageTitle(Crud::PAGE_EDIT, 'Site settings');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL, Action::BATCH_DELETE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN);
    }

    public function index(AdminContext $context): Response
    {
        if (null === $this->settings->find()) {
            $this->em->persist($this->settings->defaults());
            $this->em->flush();
            $this->settings->invalidate();
        }

        return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::EDIT)->setEntityId(1)->generateUrl());
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name')->setHelp('Shown in the header, page titles and link previews.');
        yield TextField::new('tagline')->setHelp('One line under the name on the home page; the home page description in search results.');
        yield TextareaField::new('bio')->setHelp('Shown on the About page. Leave empty, with no links, to hide the page.');
        yield TextareaField::new('links')
            ->setHelp('One per line, as "Label | https://...". Shown on the About page.');
        yield ColorField::new('accent')->setHelp('Buttons, badges, highlights, the default icon and this admin.');
        $current = $this->settings->get()->getLogoVersion();
        yield Field::new('logoUpload', 'Logo')->setFormType(FileType::class)->setFormTypeOptions(['required' => false])
            ->setHelp(null === $current
                ? 'PNG, JPEG or WebP; transparency is kept. Replaces the default mark and the name in the header, so include the name in it; also the browser icon.'
                : 'Upload to replace the current logo.');
        if (null !== $current) {
            yield BooleanField::new('removeLogo', 'Remove the logo and use the default mark')->renderAsSwitch(false);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        try {
            if (null !== $upload = $entityInstance->getLogoUpload()) {
                $this->customImages->storeLogo($entityInstance, $upload);
            } elseif ($entityInstance->isRemoveLogo()) {
                $this->customImages->deleteLogo($entityInstance);
            }
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        $this->settings->invalidate();
    }
}
