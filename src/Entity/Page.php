<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PageStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page')]
#[ORM\UniqueConstraint(name: 'page_chapter_position', columns: ['chapter_id', 'position'])]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        // Owning side. Pages are created via Chapter::getPages()->add() only
        // together with this reference, otherwise chapter_id stays NULL.
        #[ORM\ManyToOne(inversedBy: 'pages')]
        #[ORM\JoinColumn(nullable: false)]
        private Chapter $chapter,
        // Gapped (10, 20, 30...) so an insert never rewrites siblings.
        #[ORM\Column]
        private int $position,
        // Storage key of the original, never a URL.
        #[ORM\Column(length: 255)]
        private string $originalKey,
        // Null until the worker has read the image at ingest.
        #[ORM\Column(nullable: true)]
        private ?int $width = null,
        #[ORM\Column(nullable: true)]
        private ?int $height = null,
        #[ORM\Column(length: 16, enumType: PageStatus::class)]
        private PageStatus $status = PageStatus::Pending,
        // Comma-separated slot per tile of the scrambled derivative (order[tile] = slot); public, not a key.
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $tileOrder = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChapter(): Chapter
    {
        return $this->chapter;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getOriginalKey(): string
    {
        return $this->originalKey;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getTileOrder(): ?string
    {
        return $this->tileOrder;
    }

    /**
     * @param list<int> $tileOrder
     */
    public function setReadingCopy(int $width, int $height, array $tileOrder): static
    {
        $this->width = $width;
        $this->height = $height;
        $this->tileOrder = implode(',', $tileOrder);

        return $this;
    }

    public function getStatus(): PageStatus
    {
        return $this->status;
    }

    public function setStatus(PageStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
