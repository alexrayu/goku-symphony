<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SiteSettingsTest extends KernelTestCase
{
    public function testLinksAreParsedAndValidatedLineByLine(): void
    {
        $settings = (new SiteSettings('Ink'))->setLinks("Bluesky | https://bsky.app/profile/ink\n\n  Shop|https://ink.example/shop  ");

        self::assertSame([
            ['label' => 'Bluesky', 'url' => 'https://bsky.app/profile/ink'],
            ['label' => 'Shop', 'url' => 'https://ink.example/shop'],
        ], $settings->getLinkList());
        self::assertTrue($settings->hasAbout());

        $validator = static::getContainer()->get(ValidatorInterface::class);
        self::assertCount(0, $validator->validate($settings));
        // A javascript: or bare URL never reaches an href.
        self::assertCount(2, $validator->validate($settings->setLinks("Bad | javascript:alert(1)\nhttps://no-label.example")));
        self::assertSame([], $settings->getLinkList());
    }

    public function testAboutNeedsABioOrALink(): void
    {
        self::assertFalse((new SiteSettings('Ink'))->setBio("  \n")->hasAbout());
        self::assertTrue((new SiteSettings('Ink'))->setBio('Draws at night.')->hasAbout());
    }

    public function testTextOnTheAccentKeepsItsContrast(): void
    {
        $settings = new SiteSettings('Ink');
        self::assertSame('#141417', $settings->getAccentForeground(), 'Default coral takes dark text.');
        self::assertSame('#ffffff', $settings->setAccent('#1D4ED8')->getAccentForeground());
        self::assertSame('#1d4ed8', $settings->getAccent());
        self::assertSame('#141417', $settings->setAccent('#ffd166')->getAccentForeground());
    }
}
