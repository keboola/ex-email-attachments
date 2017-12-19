<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

class AddTest extends AbstractTest
{
    public function testAdd()
    {
        $result = $this->app->run(['action' => 'add', 'kbcProject' => $this->project]);
        $this->assertArrayHasKey('email', $result);
        $this->assertStringStartsWith($this->project, $result['email']);
        $idStart = strlen($this->project) + 1;
        $id = substr($result['email'], $idStart, strpos($result['email'], '@') - $idStart);

        $ruleName = sprintf("%s-%d-%s", STACK_NAME, $this->project, $id);
        $rule = $this->ses->describeReceiptRule(['RuleSetName' => RULE_SET, 'RuleName' => $ruleName]);
        $this->assertArrayHasKey('Rule', $rule);
        $this->assertArrayHasKey('Recipients', $rule['Rule']);
        $this->assertCount(1, $rule['Rule']['Recipients']);
        $this->assertEquals($result['email'], $rule['Rule']['Recipients'][0]);
        $this->assertCount(1, $rule['Rule']['Actions']);
        $this->assertArrayHasKey('S3Action', $rule['Rule']['Actions'][0]);
        $this->assertArrayHasKey('BucketName', $rule['Rule']['Actions'][0]['S3Action']);
        $this->assertArrayHasKey('ObjectKeyPrefix', $rule['Rule']['Actions'][0]['S3Action']);
        $this->assertEquals(BUCKET, $rule['Rule']['Actions'][0]['S3Action']['BucketName']);
        $this->assertEquals("$this->project/$id/", $rule['Rule']['Actions'][0]['S3Action']['ObjectKeyPrefix']);

        $this->ses->deleteReceiptRule(['RuleSetName' => RULE_SET, 'RuleName' => $ruleName]);

        $dbRow = $this->dynamo->query([
            'TableName' => DYNAMO_TABLE,
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $this->project]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
                'Email' => [
                    'AttributeValueList' => [
                        ['S' => $result['email']]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
            ],
        ]);
        $this->assertArrayHasKey('Items', $dbRow);
        $this->assertCount(1, $dbRow['Items']);
    }
}
