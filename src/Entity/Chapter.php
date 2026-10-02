<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReadingDirection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chapter')]
#[ORM\UniqueConstraint(name: 'chapter_work_number', columns: ['work_id', 'number'])]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Inverse side. cascade persist: persisting a Chapter persists new Pages
     * at flush, no explicit persist() per page. orphanRemoval: a Page removed
     * from this collection is DELETEd at flush. Both are safe because a Page
     * has exactly one owner and no life outside its Chapter.
     *
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'chapter', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $pages;

    public function __construct(
        // Owning side: holds the work_id FK. Doctrine reads only this side
        // when computing the changeset.
        #[ORM\ManyToOne(inversedBy: 'chapters')]
        #[ORM\JoinColumn(nullable: false)]
        private Work $work,
        // decimal so extras like 12.5 sort numerically. Doctrine hydrates
        // decimal as string in PHP.
        #[ORM\Column(type: 'decimal', precision: 6, scale: 1)]
        private string $number,
        #[ORM\Column(length: 16, enumType: ReadingDirection::class)]
        private ReadingDirection $direction = ReadingDirection::Ltr,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $title = null,
        #[ORM\Column(options: ['default' => false])]
        private bool $published = false,
    ) {
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWork(): Work
    {
        return $this->work;
    }

    public function setWork(Work $work): static
    {
        $this->work = $work;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDirection(): ReadingDirection
    {
        return $this->direction;
    }

    public function setDirection(ReadingDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /** @return Collection<int, Page> */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function __toString(): string
    {
        return sprintf('%s #%s', $this->work, $this->number);
    }
}
