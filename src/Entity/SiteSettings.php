<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// The artist's identity, edited in admin. A single row (id 1); until it exists, SiteSettingsProvider
// serves defaults from SITE_NAME / SITE_DESCRIPTION.
#[ORM\Entity]
#[ORM\Table(name: 'site_settings')]
class SiteSettings
{
    public const DEFAULT_ACCENT = '#ff6a4d';
    // One "Label | https://..." per line: portable text column, no JSON type.
    private const LINK = '/^(?<label>[^|]+?)\s*\|\s*(?<url>https?:\/\/\S+)$/';
    private const HEX = '/^#[0-9a-f]{6}$/i';

    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $links = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(self::HEX, message: 'Use a hex colour like #ff6a4d.')]
    private string $accent = self::DEFAULT_ACCENT;

    // Bumped on every logo upload: part of the logo URL, so a new logo is never served stale.
    #[ORM\Column(nullable: true)]
    private ?int $logoVersion = null;

    // Form-only, never persisted: processed into storage by the admin controller.
    #[Assert\Image(maxSize: '10M', mimeTypes: ['image/png', 'image/jpeg', 'image/webp'], maxWidth: 4000, maxHeight: 4000)]
    private ?File $logoUpload = null;
    private bool $removeLogo = false;

    public function __construct(
        #[ORM\Column(length: 100)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $name,
        #[ORM\Column(length: 255, nullable: true)]
        #[Assert\Length(max: 255)]
        private ?string $tagline = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): static
    {
        $this->tagline = $tagline;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getLinks(): ?string
    {
        return $this->links;
    }

    public function setLinks(?string $links): static
    {
        $this->links = $links;

        return $this;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function getLinkList(): array
    {
        $list = [];
        foreach ($this->linkLines() as $line) {
            if (1 === preg_match(self::LINK, $line, $match)) {
                $list[] = ['label' => $match['label'], 'url' => $match['url']];
            }
        }

        return $list;
    }

    // The About page exists only when there is something to say.
    public function hasAbout(): bool
    {
        return null !== $this->bio && '' !== trim($this->bio) || [] !== $this->getLinkList();
    }

    // Always a valid hex colour, so templates may print it raw into CSS and SVG.
    public function getAccent(): string
    {
        return 1 === preg_match(self::HEX, $this->accent) ? $this->accent : self::DEFAULT_ACCENT;
    }

    public function setAccent(string $accent): static
    {
        $this->accent = strtolower($accent);

        return $this;
    }

    // Text on accent-coloured buttons: dark on light accents, white on dark ones (WCAG relative luminance).
    public function getAccentForeground(): string
    {
        $channels = array_map(static function (string $hex): float {
            $c = hexdec($hex) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($this->getAccent(), 1), 2));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        // Contrast against #141417 and against white is equal at about 0.195.
        return $luminance > 0.195 ? '#141417' : '#ffffff';
    }

    public function getLogoVersion(): ?int
    {
        return $this->logoVersion;
    }

    public function setLogoVersion(?int $logoVersion): static
    {
        $this->logoVersion = $logoVersion;

        return $this;
    }

    public function getLogoUpload(): ?File
    {
        return $this->logoUpload;
    }

    public function setLogoUpload(?File $logoUpload): static
    {
        $this->logoUpload = $logoUpload;

        return $this;
    }

    public function isRemoveLogo(): bool
    {
        return $this->removeLogo;
    }

    public function setRemoveLogo(bool $removeLogo): static
    {
        $this->removeLogo = $removeLogo;

        return $this;
    }

    #[Assert\Callback]
    public function validateLinks(ExecutionContextInterface $context): void
    {
        foreach ($this->linkLines() as $line) {
            if (1 !== preg_match(self::LINK, $line)) {
                $context->buildViolation('Write each link as "Label | https://...": {{ line }}')
                    ->setParameter('{{ line }}', $line)
                    ->atPath('links')
                    ->addViolation();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function linkLines(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $this->links)), static fn (string $l): bool => '' !== $l));
    }
}
