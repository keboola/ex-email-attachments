<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

use Keboola\Pigeon\App;

class AddTest extends AbstractTest
{
    public function testAdd()
    {
        $config = uniqid();
        $result = App::execute(
            $this->appConfiguration,
            ['action' => 'add', 'kbcProject' => $this->project, 'config' => $config],
            $this->temp
        );
        $this->assertArrayHasKey('email', $result);
        $this->assertStringStartsWith($this->project, $result['email']);
        preg_match('/^\d+-(.+)@' . EMAIL_DOMAIN . '/', $result['email'], $match);

        $dbRow = $this->dynamo->query([
            'TableName' => DYNAMO_TABLE,
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $this->project]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
                'Config' => [
                    'AttributeValueList' => [
                        ['S' => $config]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
            ],
        ]);
        $this->assertArrayHasKey('Items', $dbRow);
        $this->assertCount(1, $dbRow['Items']);
        $this->assertArrayHasKey('Email', $dbRow['Items'][0]);
        $this->assertArrayHasKey('S', $dbRow['Items'][0]['Email']);
        $this->assertEquals($result['email'], $dbRow['Items'][0]['Email']['S']);

        // Call add once more but it should return the same email
        $result2 = App::execute(
            $this->appConfiguration,
            ['action' => 'add', 'kbcProject' => $this->project, 'config' => $config],
            $this->temp
        );
        $this->assertArrayHasKey('email', $result2);
        $this->assertEquals($result['email'], $result2['email']);
    }
}
