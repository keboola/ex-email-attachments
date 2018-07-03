<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Tests\Unit;

use Keboola\ExEmailAttachments\Action\RunAction;
use Keboola\Temp\Temp;
use PhpMimeMailParser\Parser;

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
        ], $this->temp);
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
        $email = "771-367243369-5aaa21c4ae622@import.keboola.com";
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

    public function testSaveFileWithMultipleAttachments()
    {
        $parser = new Parser();
        $parser->setPath(__DIR__ . '/../email-with-image');
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');

        $userConfig = ['outputPath' => $this->temp->getTmpFolder()];
        $attachments = $parser->getAttachments();

        // First file is an image so it should be ignored
        $this->assertEmpty($this->runAction->saveFile($userConfig, $attachments[0]));

        // Second file is a csv so its name should be returned
        $this->assertStringStartsWith('test.csv', $this->runAction->saveFile($userConfig, $attachments[1]));
    }
}
