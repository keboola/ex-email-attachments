<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Tests\Unit;

use Keboola\ExEmailAttachments\Action\RunAction;
use Keboola\Temp\Temp;

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
}
