<?php

namespace Keboola\ExEmailAttachments\Tests\Unit;

use Keboola\ExEmailAttachments\Action\RunAction;
use Keboola\ExEmailAttachments\Exception\InvalidEmailRecipientException;
use Keboola\ExEmailAttachments\Exception\MoreAttachmentsInEmailException;
use Keboola\ExEmailAttachments\Exception\NoAttachmentInEmailException;
use Keboola\Temp\Temp;
use PhpMimeMailParser\Parser;
use Symfony\Component\Console\Output\NullOutput;

class RunActionTest extends \PHPUnit\Framework\TestCase
{
    /** @var Temp */
    protected $temp;
    /** @var RunAction */
    protected $runAction;
    protected $project;
    protected $id;
    protected $config;
    protected $email;

    protected function setUp()
    {
        $this->temp = new Temp();
        $this->temp->initRunFolder();
        $this->runAction = new RunAction([
            'accessKeyId' => 'accessKeyId',
            'secretAccessKey' => 'secretAccessKey',
            'region' => 'us-wast-1',
            'bucket' => 'bucket',
            'emailDomain' => 'test.com',
            'dynamoTable' => 'table',
        ], $this->temp, new NullOutput());
        $this->project = rand(1, 1000);
        $this->id = uniqid();
        $this->config = uniqid();
        $this->email = "{$this->project}-{$this->config}-{$this->id}@" . EMAIL_DOMAIN;
    }

    public function testCheckEmailInRecipients()
    {
        $this->assertTrue($this->runAction->checkEmailInRecipients([$this->email], $this->email));
        $this->assertTrue($this->runAction->checkEmailInRecipients([$this->email, 'user@test.com'], $this->email));
        $this->assertTrue($this->runAction->checkEmailInRecipients([" {$this->email} "], $this->email));
        $this->assertFalse($this->runAction->checkEmailInRecipients([" {$this->email}x "], $this->email));
        $this->assertTrue($this->runAction->checkEmailInRecipients(["<{$this->email}> "], $this->email));
        $this->assertTrue($this->runAction->checkEmailInRecipients(["\"\" <{$this->email}>"], $this->email));
        $this->assertTrue($this->runAction->checkEmailInRecipients(["\"Test\" <{$this->email}>"], $this->email));
    }

    public function testCheckEmailInRecipientsWithParserCc()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-cc'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);

        $this->assertTrue($this->runAction->checkEmailInRecipients([
            $parser->getHeader('to'),
            $parser->getHeader('cc'),
            $parser->getHeader('bcc'),
        ], $email));
    }

    public function testCheckEmailInRecipientsWithParserMultipleTo()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-multiple-to'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);

        $this->assertTrue($this->runAction->checkEmailInRecipients([
            $parser->getHeader('to'),
            $parser->getHeader('cc'),
            $parser->getHeader('bcc'),
        ], $email));
    }

    public function testParseEmailFromS3FileInvalidRecipient()
    {
        $email = "xx@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();

        $this->expectException(InvalidEmailRecipientException::class);
        $this->runAction->parseEmailFromS3File($tempFile, $email);
    }

    public function testParseEmailFromS3FileOk()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-multiple-to'))
        );

        $result = $this->runAction->parseEmailFromS3File($tempFile, $email);
        $this->assertCount(1, $result->getAttachments());
    }

    public function testGetFromAndDateClause()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-multiple-to'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);
        $this->assertEquals(
            "Email sent by test@keboola.com received on Tue, 19 Dec 2017 16:07:40 +0100",
            $this->runAction->getFromAndDateClause($parser)
        );
    }

    public function testGetTextAttachmentOk()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-with-image'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');

        $this->assertStringEndsWith('test.csv', $this->runAction->getTextAttachment($parser));
    }

    public function testGetTextAttachmentNoText()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-just-image'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');

        $this->expectException(NoAttachmentInEmailException::class);
        $this->runAction->getTextAttachment($parser);
    }

    public function testGetTextAttachmentMoreTexts()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        file_put_contents(
            $tempFile,
            str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email-more-texts'))
        );

        $parser = new Parser();
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');

        $this->expectException(MoreAttachmentsInEmailException::class);
        $this->runAction->getTextAttachment($parser);
    }

    public function testSaveFiles()
    {
        $tempFile1 = $this->temp->createTmpFile()->getRealPath();
        file_put_contents($tempFile1, "id,name,order\n1,a,11\n2,b,22");
        $tempFile2 = $this->temp->createTmpFile()->getRealPath();
        file_put_contents($tempFile2, "id2,name2,order2\n3,c,33\n4,d,44");

        $this->runAction->saveFiles([
            'outputPath' => $this->temp->getTmpFolder(),
            'incremental' => true,
            'enclosure' => '"',
            'delimiter' => ',',
            'primaryKey' => ['id'],
        ], [$tempFile1, $tempFile2]);

        $this->assertFileExists("{$this->temp->getTmpFolder()}/data.csv.manifest");
        $manifest = json_decode(file_get_contents("{$this->temp->getTmpFolder()}/data.csv.manifest"), true);
        $this->assertArrayHasKey('columns', $manifest);
        $this->assertEquals(['id', 'name', 'order'], $manifest['columns']);

        $this->assertDirectoryExists("{$this->temp->getTmpFolder()}/data.csv");
        $csvFiles = glob($this->temp->getTmpFolder().'/data.csv/*');
        $this->assertCount(2, $csvFiles);

        $file1 = file($csvFiles[0]);
        $this->assertCount(2, $file1);
        $this->assertNotEquals('id,name,order', $file1[0]);
        $file2 = file($csvFiles[1]);
        $this->assertCount(2, $file2);
        $this->assertNotEquals('id,name,order', $file1[0]);
    }
}
