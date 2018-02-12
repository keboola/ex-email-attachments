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
use Keboola\Temp\Temp;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractTest extends \PHPUnit\Framework\TestCase
{
    protected $project;
    protected $outputPath;
    /** @var DynamoDbClient */
    protected $dynamo;
    /** @var SesClient */
    protected $ses;
    /** @var S3Client */
    protected $s3;
    protected $appConfiguration;
    /** @var Temp */
    protected $temp;

    protected function setUp()
    {
        parent::setUp();
        $this->dynamo = new DynamoDbClient([
            'credentials'=> [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2012-08-10',
        ]);
        $this->ses = new SesClient([
            'credentials'=> [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2010-12-01',
        ]);
        $this->s3 = new S3Client([
            'credentials'=> [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
            'region' => REGION,
            'version' => '2006-03-01',
        ]);
        $this->project = (string)rand(1100, 1200);

        $this->outputPath = '/data/out/tables';
        (new Filesystem())->mkdir([$this->outputPath]);
        $this->appConfiguration = [
            'accessKeyId' => AWS_ACCESS_KEY_ID,
            'secretAccessKey' => AWS_SECRET_ACCESS_KEY,
            'region' => REGION,
            'bucket' => S3_BUCKET,
            'emailDomain' => EMAIL_DOMAIN,
            'dynamoTable' => DYNAMO_TABLE,
        ];
        $this->temp = new Temp();

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
                                    'Config' => ['S' => $row['Config']['S']],
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
        $this->s3->deleteMatchingObjects(S3_BUCKET, "$this->project/");
    }
}
