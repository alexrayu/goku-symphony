<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkType;
use App\Repository\WorkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(length: 255, unique: true)]
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

    public function __toString(): string
    {
        return $this->title;
    }
}
