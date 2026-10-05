<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Ingest\Message\IngestArchive;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChapterUploadTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel for the test, so the in-memory transport keeps what the request sent.
        $this->client->disableReboot();
        $this->client->loginUser(UserFactory::createOne());
    }

    protected function tearDown(): void
    {
        static::getContainer()->get('default.storage')->deleteDirectory('incoming');
        parent::tearDown();
    }

    public function testArchiveIsStoredAndQueued(): void
    {
        $chapter = ChapterFactory::createOne();

        $this->submit((int) $chapter->getId(), $this->zipFile());

        self::assertResponseRedirects();
        $sent = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $sent);
        self::assertCount(1, $sent->getSent());
        $message = $sent->getSent()[0]->getMessage();
        self::assertInstanceOf(IngestArchive::class, $message);
        self::assertTrue(static::getContainer()->get('default.storage')->fileExists($message->archiveKey));
    }

    public function testNonZipIsRejected(): void
    {
        $chapter = ChapterFactory::createOne();
        $text = tempnam(sys_get_temp_dir(), 'up').'.zip';
        file_put_contents($text, 'not a zip');

        $this->submit((int) $chapter->getId(), $text);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'Upload a .zip or .cbz archive.');
    }

    public function testChapterWithPagesIsRejected(): void
    {
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter]);

        $this->submit((int) $chapter->getId(), $this->zipFile());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'already has pages');
    }

    private function submit(int $chapterId, string $file): Crawler
    {
        $crawler = $this->client->request('GET', sprintf('/admin/chapter/%d/upload', $chapterId));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Upload')->form();
        $field = $form['archive'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($file);

        return $this->client->submit($form);
    }

    private function zipFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'up').'.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('page_1.png', 'x');
        $zip->close();

        return $path;
    }
}
