<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Tests\Functional;

use Keboola\ExEmailAttachments\App;

class ListTest extends AbstractTest
{
    public function testList()
    {
        $config1 = uniqid();
        $config2 = uniqid();
        $email1 = uniqid() . '@' . EMAIL_DOMAIN;
        $email2 = uniqid() . '@' . EMAIL_DOMAIN;
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Config' => ['S' => $config1],
                'Email' => ['S' => $email1],
            ],
        ]);
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Config' => ['S' => $config2],
                'Email' => ['S' => $email2],
            ],
        ]);

        $result = App::execute(
            $this->appConfiguration,
            ['action' => 'list', 'kbcProject' => $this->project],
            $this->temp
        );
        $this->assertCount(2, $result);
        $this->assertArraySubset([$email1, $email2], $result);
    }
}
