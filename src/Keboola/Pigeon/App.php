<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon;

use Aws\DynamoDb\DynamoDbClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Keboola\Temp\Temp;
use PhpMimeMailParser\Parser;

class App
{
    /** @var Temp  */
    protected $temp;
    protected $appConfiguration;
    protected $emailDomain;
    protected $bucketName;
    protected $ruleSetName;

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


    public function run($userConfiguration)
    {
        switch ($userConfiguration['action']) {
            case 'run':
                return $this->runAction($userConfiguration);
                break;
            case 'add':
                return $this->addAction($userConfiguration);
                break;
            case 'list':
                return $this->listAction($userConfiguration);
                break;
            default:
                throw new Exception("Action {$userConfiguration['action']} is not supported");
        }
    }

    public function runAction($userConfiguration)
    {
        preg_match("/^\d+-(.+)@{$this->appConfiguration['emailDomain']}/", $userConfiguration['email'], $match);
        if (count($match) < 2) {
            throw new Exception('Email address is not configured for the project');
        }
        $id = $match[1];
        $dynamo = $this->initDynamoDb();
        $result = $dynamo->query([
            'TableName' => $this->appConfiguration['dynamoTable'],
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $userConfiguration['kbcProject']]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
                'Email' => [
                    'AttributeValueList' => [
                        ['S' => $userConfiguration['email']]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
            ],
        ]);
        if (!$result['Count']) {
            throw new Exception('Email address is not configured for the project');
        }

        $this->temp->initRunFolder();
        $s3 = $this->initS3();
        try {
            $objects = $s3->listObjectsV2([
                'Bucket' => $this->appConfiguration['bucket'],
                'Prefix' => "{$userConfiguration['kbcProject']}/{$id}/",
            ]);
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() != 'AccessDenied') {
                throw $e;
            }
            // If error code is AccessDenied, there probably is not any email yet and so the folder does not exist
            return ['processedAttachments' => 0];
        }

        $processedAttachments = 0;
        $parser = new Parser();
        foreach ($objects['Contents'] as $file) {
            if ($file['Key'] != "{$userConfiguration['kbcProject']}/{$id}/AMAZON_SES_SETUP_NOTIFICATION") {
                $tempFile = $this->temp->createTmpFile()->getRealPath();
                $s3->getObject([
                    'Bucket' => $this->appConfiguration['bucket'],
                    'Key' => $file['Key'],
                    'SaveAs' => $tempFile,
                ]);
                $parser->setPath($tempFile);
                $parser->saveAttachments($this->temp->getTmpFolder() . '/');
                $attachments = $parser->getAttachments();
                if (count($attachments) > 0) {
                    foreach ($attachments as $attachment) {
                        $fileName = "{$this->appConfiguration['outputPath']}/{$userConfiguration['table']['source']}";
                        rename("{$this->temp->getTmpFolder()}/{$attachment->getFilename()}", $fileName);
                        $manifest = ['destination' => $userConfiguration['table']['destination']];
                        if (isset($userConfiguration['incremental'])) {
                            $manifest['incremental'] = (bool)$userConfiguration['incremental'];
                        }
                        if (isset($userConfiguration['delimeter'])) {
                            $manifest['delimeter'] = $userConfiguration['delimeter'];
                        }
                        if (isset($userConfiguration['enclosure'])) {
                            $manifest['enclosure'] = $userConfiguration['enclosure'];
                        }
                        if (count($manifest)) {
                            file_put_contents("$fileName.manifest", json_encode($manifest));
                        }
                        $processedAttachments++;
                    }
                }
            }
        }
        return ['processedAttachments' => $processedAttachments];
    }

    public function addAction($userConfiguration)
    {
        $id = uniqid();
        $email = sprintf(
            '%s-%s@%s',
            $userConfiguration['kbcProject'],
            $id,
            $this->appConfiguration['emailDomain']
        );
        $ses = $this->initSes();
        $ses->createReceiptRule([
            'Rule' => [
                'Name' => "{$this->appConfiguration['stackName']}-{$userConfiguration['kbcProject']}-$id",
                'Enabled' => true,
                'Actions' => [
                    [
                        'S3Action' => [
                            'BucketName' => $this->appConfiguration['bucket'],
                            'ObjectKeyPrefix' => "{$userConfiguration['kbcProject']}/$id/",
                        ],
                    ],
                ],
                'Recipients' => [$email],
            ],
            'RuleSetName' => $this->appConfiguration['ruleSet'],
        ]);
        $dynamo = $this->initDynamoDb();
        $dynamo->putItem([
            'TableName' => $this->appConfiguration['dynamoTable'],
            'Item' => [
                'Project' => ['N' => $userConfiguration['kbcProject']],
                'Email' => ['S' => $email],
            ],
        ]);
        return ['email' => $email];
    }

    public function listAction($userConfiguration)
    {
        $dynamo = $this->initDynamoDb();
        $result = $dynamo->query([
            'TableName' => $this->appConfiguration['dynamoTable'],
            'KeyConditions' => [
                'Project' => [
                    'AttributeValueList' => [
                        ['N' => $userConfiguration['kbcProject']]
                    ],
                    'ComparisonOperator' => 'EQ'
                ],
            ],
        ]);
        return array_map(function ($row) {
            return $row['Email']['S'];
        }, $result['Items']);
    }
}
