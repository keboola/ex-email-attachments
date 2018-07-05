<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Tests\Functional;

use Keboola\ExEmailAttachments\App;
use Symfony\Component\Console\Output\NullOutput;

class GetTest extends AbstractTest
{
    public function testGet()
    {
        $config = uniqid();
        $result = App::execute(
            $this->appConfiguration,
            ['action' => 'get', 'kbcProject' => $this->project, 'config' => $config],
            $this->temp,
            new NullOutput()
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
            ['action' => 'get', 'kbcProject' => $this->project, 'config' => $config],
            $this->temp,
            new NullOutput()
        );
        $this->assertArrayHasKey('email', $result2);
        $this->assertEquals($result['email'], $result2['email']);
    }
}
