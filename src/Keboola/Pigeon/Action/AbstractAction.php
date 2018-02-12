<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Action;

use Aws\DynamoDb\DynamoDbClient;
use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Keboola\Pigeon\Exception;
use Keboola\Temp\Temp;

abstract class AbstractAction
{
    /** @var Temp  */
    protected $temp;
    protected $appConfiguration;
    protected $emailDomain;
    protected $bucketName;

    public function __construct($appConfiguration, Temp $temp)
    {
        $this->appConfiguration = $appConfiguration;
        $this->temp = $temp;
    }

    protected function getAwsCredentials()
    {
        return [
            'credentials'=> [
                'key' => $this->appConfiguration['accessKeyId'],
                'secret' => $this->appConfiguration['secretAccessKey'],
            ],
            'region' => $this->appConfiguration['region'],
        ];
    }

    protected function initSes()
    {
        return new SesClient(array_merge($this->getAwsCredentials(), [
            'version' => '2010-12-01',
        ]));
    }

    protected function initS3()
    {
        return new S3Client(array_merge($this->getAwsCredentials(), [
            'version' => '2006-03-01',
        ]));
    }

    protected function initDynamoDb()
    {
        return new DynamoDbClient(array_merge($this->getAwsCredentials(), [
            'version' => '2012-08-10',
        ]));
    }

    protected function getDbRecord(DynamoDbClient $dynamo, $kbcProject, $config)
    {
        $result = $dynamo->query([
            'TableName' => $this->appConfiguration['dynamoTable'],
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $kbcProject],
                    ],
                    'ComparisonOperator' => 'EQ',
                ],
                'Config' => [
                    'AttributeValueList' => [
                        ['S' => $config],
                    ],
                    'ComparisonOperator' => 'EQ',
                ],
            ],
        ]);
        if (!$result['Count']) {
            throw new Exception('Email address is not configured for the project');
        }
        return $result['Items'][0]['Email']['S'];
    }
}
