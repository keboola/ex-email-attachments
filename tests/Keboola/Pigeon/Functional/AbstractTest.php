<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

use Aws\DynamoDb\DynamoDbClient;
use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Keboola\Pigeon\App;
use Keboola\Temp\Temp;

abstract class AbstractTest extends \PHPUnit\Framework\TestCase
{
    protected $project;
    /** @var DynamoDbClient */
    protected $dynamo;
    /** @var SesClient */
    protected $ses;
    /** @var S3Client */
    protected $s3;
    /** @var App */
    protected $app;

    protected function setUp()
    {
        parent::setUp();
        $this->dynamo = new DynamoDbClient([
            'credentials'=> [
                'key' => ACCESS_KEY_ID,
                'secret' => SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2012-08-10',
        ]);
        $this->ses = new SesClient([
            'credentials'=> [
                'key' => ACCESS_KEY_ID,
                'secret' => SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2010-12-01',
        ]);
        $this->s3 = new S3Client([
            'credentials'=> [
                'key' => ACCESS_KEY_ID,
                'secret' => SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2006-03-01',
        ]);
        $this->project = (string)rand(1100, 1200);
        $this->app = new App([
            'accessKeyId' => ACCESS_KEY_ID,
            'secretAccessKey' => SECRET_ACCESS_KEY,
            'region' => REGION,
            'bucket' => BUCKET,
            'emailDomain' => EMAIL_DOMAIN,
            'ruleSet' => RULE_SET,
            'dynamoTable' => DYNAMO_TABLE,
            'stackName' => STACK_NAME,
        ], new Temp());

        $this->cleanDynamo();
        $this->cleanS3();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->cleanDynamo();
        $this->cleanS3();
    }

    protected function cleanDynamo()
    {
        $result = $this->dynamo->query([
            'TableName' => DYNAMO_TABLE,
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $this->project],
                    ],
                    'ComparisonOperator' => 'EQ',
                ],
            ],
        ]);
        if (count($result['Items'])) {
            $this->dynamo->batchWriteItem([
                'RequestItems' => [
                    DYNAMO_TABLE => array_map(function ($row) {
                        return [
                            'DeleteRequest' => [
                                'Key' => [
                                    'Project' => ['N' => $this->project],
                                    'Email' => ['S' => $row['Email']['S']],
                                ],
                            ],
                        ];
                    }, $result['Items'])
                ],
            ]);
        }
    }

    protected function cleanS3()
    {
        $this->s3->deleteMatchingObjects(BUCKET, "$this->project/");
    }
}
