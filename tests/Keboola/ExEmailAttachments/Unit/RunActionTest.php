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
    public function testGetAddressFromTo()
    {
        $runAction = new RunAction([
            'accessKeyId' => 'accessKeyId',
            'secretAccessKey' => 'secretAccessKey',
            'region' => 'us-wast-1',
            'bucket' => 'bucket',
            'emailDomain' => 'test.com',
            'dynamoTable' => 'table',
        ], new Temp());
        $this->assertEquals('test@email.com', $runAction->getAddressFromTo('test@email.com'));
        $this->assertEquals('test@email.com', $runAction->getAddressFromTo(' test@email.com '));
        $this->assertEquals('test@email.com', $runAction->getAddressFromTo('<test@email.com>'));
        $this->assertEquals('test@email.com', $runAction->getAddressFromTo('"" <test@email.com>'));
        $this->assertEquals('test@email.com', $runAction->getAddressFromTo('"Test" <test@email.com>'));
    }
}
