<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

class ListTest extends AbstractTest
{
    public function testList()
    {
        $email1 = uniqid() . '@' . EMAIL_DOMAIN;
        $email2 = uniqid() . '@' . EMAIL_DOMAIN;
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Email' => ['S' => $email1],
            ],
        ]);
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Email' => ['S' => $email2],
            ],
        ]);

        $result = $this->app->run(['action' => 'list', 'kbcProject' => $this->project]);
        $this->assertCount(2, $result);
        $this->assertArraySubset([$email1, $email2], $result);
    }
}
