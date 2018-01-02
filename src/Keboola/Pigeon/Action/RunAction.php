<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Action;

use Aws\Api\DateTimeResult;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Keboola\Pigeon\Exception;
use PhpMimeMailParser\Attachment;
use PhpMimeMailParser\Parser;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class RunAction extends AbstractAction
{
    /** @var S3Client */
    protected $s3Client;
    protected $lastTimestamp;

    public function execute($userConfiguration)
    {
        $emailId = $this->getEmailIdFromConfiguration($userConfiguration);
        $this->checkDbRecord($userConfiguration);

        $this->lastTimestamp = isset($userConfiguration['state']['lastDownloadedFileTimestamp'])
            ? $userConfiguration['state']['lastDownloadedFileTimestamp'] : 0;

        $this->temp->initRunFolder();
        $this->s3Client = $this->initS3();

        $filesToDownload = $this->listS3Files($userConfiguration['kbcProject'], $emailId);

        // Filter out processed files
        $filesToDownload = array_filter($filesToDownload, function ($fileToDownload) {
            /** @var DateTimeResult $lastModified */
            if ($fileToDownload["timestamp"] > $this->lastTimestamp) {
                return true;
            }
            return false;
        });

        $processed = 0;
        foreach ($filesToDownload as $fileToDownload) {
            $processed += $this->getS3File($fileToDownload['key'], $fileToDownload['timestamp'], $userConfiguration);
        }
        $this->saveState($userConfiguration);
        return ['processedAttachments' => $processed];
    }

    protected function listS3Files($kbcProject, $emailId)
    {
        try {
            $objects = $this->s3Client->getIterator('ListObjects', [
                'Bucket' => $this->appConfiguration['bucket'],
                'Prefix' => "{$kbcProject}/{$emailId}/",
            ]);
            $filesToDownload = [];
            foreach ($objects as $object) {
                if ($object['Key'] === "{$kbcProject}/{$emailId}/AMAZON_SES_SETUP_NOTIFICATION") {
                    continue;
                }
                /** @noinspection PhpUndefinedMethodInspection */
                $filesToDownload[] = [
                    'timestamp' => $object['LastModified']->format('U'),
                    'key' => $object['Key'],
                ];
            }
            return $filesToDownload;
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() != 'AccessDenied') {
                throw $e;
            }
            // If error code is AccessDenied, there probably is not any email yet and so the folder does not exist
            return [];
        }
    }

    public function getS3File($fileKey, $timestamp, $userConfiguration)
    {
        $processedAttachments = 0;
        $parser = new Parser();
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        $this->s3Client->getObject([
            'Bucket' => $this->appConfiguration['bucket'],
            'Key' => $fileKey,
            'SaveAs' => $tempFile,
        ]);
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');
        $attachments = $parser->getAttachments();
        if (count($attachments) > 0) {
            foreach ($attachments as $attachment) {
                $this->saveFile($userConfiguration, $attachment);
                $processedAttachments++;
            }
        }
        $this->lastTimestamp = max($this->lastTimestamp, $timestamp);
        return $processedAttachments;
    }

    protected function getEmailIdFromConfiguration($userConfiguration)
    {
        preg_match("/^\d+-(.+)@{$this->appConfiguration['emailDomain']}/", $userConfiguration['email'], $match);
        if (count($match) < 2) {
            throw new Exception('Email address is not configured for the project');
        }
        return $match[1];
    }

    protected function checkDbRecord($userConfiguration)
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
    }

    protected function saveFile($userConfiguration, Attachment $attachment)
    {
        $fileName = "{$userConfiguration['outputPath']}/{$userConfiguration['table']['source']}";
        rename("{$this->temp->getTmpFolder()}/{$attachment->getFilename()}", $fileName);
        $manifest = ['destination' => $userConfiguration['table']['destination']];
        if (isset($userConfiguration['incremental'])) {
            $manifest['incremental'] = (bool)$userConfiguration['incremental'];
        }
        if (!empty($userConfiguration['delimiter'])) {
            $manifest['delimiter'] = $userConfiguration['delimiter'];
        }
        if (!empty($userConfiguration['enclosure'])) {
            $manifest['enclosure'] = $userConfiguration['enclosure'];
        }
        file_put_contents("$fileName.manifest", json_encode($manifest));
    }

    protected function saveState($userConfiguration)
    {
        $outputStateFile = "{$userConfiguration['outputPath']}/../state.json";
        $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();
        file_put_contents($outputStateFile, $jsonEncode->encode(
            ['lastDownloadedFileTimestamp' => $this->lastTimestamp],
            JsonEncoder::FORMAT
        ));
    }
}
