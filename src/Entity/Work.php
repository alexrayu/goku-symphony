<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkType;
use App\Repository\WorkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Table(name: 'work')]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Inverse side: no column here, the FK lives on Chapter.work_id.
     * No cascade and no orphanRemoval: deleting a Work that still has
     * chapters must fail on the FK, not silently wipe published content.
     *
     * @var Collection<int, Chapter>
     */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'work')]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $chapters;

    // Set when a chosen cover is uploaded, bumped on each replacement and part of its URLs.
    // Null: the first chapter's first page serves as the cover.
    #[ORM\Column(nullable: true)]
    private ?int $coverVersion = null;

    // Form-only, never persisted: processed into storage by the admin controller.
    #[Assert\Image(maxSize: '20M', mimeTypes: ['image/png', 'image/jpeg', 'image/webp'], maxWidth: 8000, maxHeight: 8000)]
    private ?File $coverUpload = null;
    private bool $removeCover = false;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $title,
        // Public URL /{slug}. The reserved names are the fixed top-level paths, which match before work URLs.
        #[ORM\Column(length: 255, unique: true)]
        #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Use lowercase letters, digits and single hyphens.')]
        #[Assert\Regex('/^(admin|login|logout|install|media|assets|bundles|about)$/', message: 'This slug is reserved for a site page.', match: false)]
        private string $slug,
        #[ORM\Column(length: 16, enumType: WorkType::class)]
        private WorkType $type = WorkType::Series,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
    ) {
        $this->chapters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getType(): WorkType
    {
        return $this->type;
    }

    public function setType(WorkType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return Collection<int, Chapter> */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function getCoverVersion(): ?int
    {
        return $this->coverVersion;
    }

    public function setCoverVersion(?int $coverVersion): static
    {
        $this->coverVersion = $coverVersion;

        return $this;
    }

    public function getCoverUpload(): ?File
    {
        return $this->coverUpload;
    }

    public function setCoverUpload(?File $coverUpload): static
    {
        $this->coverUpload = $coverUpload;

        return $this;
    }

    public function isRemoveCover(): bool
    {
        return $this->removeCover;
    }

    public function setRemoveCover(bool $removeCover): static
    {
        $this->removeCover = $removeCover;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    // A oneshot shows exactly one chapter: see Chapter::validateOneshot() for the other side.
    #[Assert\Callback]
    public function validateOneshot(ExecutionContextInterface $context): void
    {
        if (WorkType::Oneshot === $this->type && $this->chapters->count() > 1) {
            $context->buildViolation('A oneshot has a single chapter; this work has {{ count }}.')
                ->setParameter('{{ count }}', (string) $this->chapters->count())
                ->atPath('type')
                ->addViolation();
        }
    }
}
