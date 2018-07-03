<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Action;

use Aws\Api\DateTimeResult;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use PhpMimeMailParser\Attachment;
use PhpMimeMailParser\Parser;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class RunAction extends AbstractAction
{
    /** @var S3Client */
    protected $s3Client;
    protected $lastDownloadedFileTimestamp;
    protected $processedFilesInLastTimestampSecond;

    public function execute($userConfiguration)
    {
        $dynamo = $this->initDynamoDb();
        $email = $this->getDbRecord($dynamo, $userConfiguration['kbcProject'], $userConfiguration['config']);

        $this->temp->initRunFolder();
        $this->s3Client = $this->initS3();

        $this->readState($userConfiguration);
        $filesToDownload = $this->listS3Files($userConfiguration['kbcProject'], $userConfiguration['config']);

        // Filter out processed files
        $filesToDownload = array_filter($filesToDownload, function ($fileToDownload) {
            /** @var DateTimeResult $lastModified */
            if ($fileToDownload['timestamp'] < $this->lastDownloadedFileTimestamp) {
                return false;
            }
            if (in_array($fileToDownload['key'], $this->processedFilesInLastTimestampSecond)) {
                return false;
            }
            return true;
        });

        $processed = [];
        foreach ($filesToDownload as $fileToDownload) {
            $processed += $this->getS3File(
                $fileToDownload['key'],
                $fileToDownload['timestamp'],
                $userConfiguration,
                $email
            );
        }
        $this->saveState($userConfiguration);
        return $processed;
    }

    protected function listS3Files($kbcProject, $config)
    {
        try {
            $objects = $this->s3Client->getIterator('ListObjects', [
                'Bucket' => $this->appConfiguration['bucket'],
                'Prefix' => "{$kbcProject}/{$config}/",
            ]);
            $filesToDownload = [];
            foreach ($objects as $object) {
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

    public function getAddressesFromEmailField($field)
    {
        $result = [];
        foreach (mailparse_rfc822_parse_addresses($field) as $item) {
            $result[] = trim($item['address']);
        }
        return $result;
    }

    public function checkEmailInRecipients($fields, $email)
    {
        foreach ($fields as $field) {
            if (in_array($email, $this->getAddressesFromEmailField($field))) {
                return true;
            }
        }
        return false;
    }

    public function getS3File($fileKey, $timestamp, $userConfiguration, $email)
    {
        $parser = new Parser();
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        $this->s3Client->getObject([
            'Bucket' => $this->appConfiguration['bucket'],
            'Key' => $fileKey,
            'SaveAs' => $tempFile,
        ]);
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');

        if (!$this->checkEmailInRecipients([
            $parser->getHeader('to'),
            $parser->getHeader('cc'),
            $parser->getHeader('bcc'),
        ], $email)) {
            return [];
        }

        $processed = [];
        $attachments = $parser->getAttachments();
        if (count($attachments) > 0) {
            foreach ($attachments as $attachment) {
                $file = $this->saveFile($userConfiguration, $attachment);
                if ($file) {
                    $processed[] = $file;
                }
            }
        }
        if ($this->lastDownloadedFileTimestamp != $timestamp) {
            $this->processedFilesInLastTimestampSecond = [];
        }
        $this->lastDownloadedFileTimestamp = max($this->lastDownloadedFileTimestamp, $timestamp);
        $this->processedFilesInLastTimestampSecond[] = $fileKey;
        return [$this->getAddressesFromEmailField($parser->getHeader('From'))[0] => $processed];
    }

    public function saveFile($userConfiguration, Attachment $attachment)
    {
        $oldFileName = "{$this->temp->getTmpFolder()}/{$attachment->getFilename()}";
        if (substr(mime_content_type($oldFileName), 0, 5) !== 'text/') {
            return null;
        }
        $newFileName = "{$userConfiguration['outputPath']}/data.csv";
        rename($oldFileName, $newFileName);
        $manifest = [];
        if (isset($userConfiguration['incremental'])) {
            $manifest['incremental'] = (bool)$userConfiguration['incremental'];
        }
        if (!empty($userConfiguration['delimiter'])) {
            $manifest['delimiter'] = $userConfiguration['delimiter'];
        }
        if (!empty($userConfiguration['enclosure'])) {
            $manifest['enclosure'] = $userConfiguration['enclosure'];
        }
        if (!empty($userConfiguration['primaryKey'])) {
            $manifest['primary_key'] = $userConfiguration['primaryKey'];
        }
        if ($manifest) {
            file_put_contents("$newFileName.manifest", json_encode($manifest));
        }
        return $attachment->getFilename(). '(' . filesize($newFileName) . ' B)';
    }

    protected function readState($userConfiguration)
    {
        $this->lastDownloadedFileTimestamp = isset($userConfiguration['state']['lastDownloadedFileTimestamp'])
            ? $userConfiguration['state']['lastDownloadedFileTimestamp'] : 0;
        $this->processedFilesInLastTimestampSecond = isset($userConfiguration['state']['processedFilesInLastTimestampSecond'])
            ? $userConfiguration['state']['processedFilesInLastTimestampSecond'] : [];
    }

    protected function saveState($userConfiguration)
    {
        $outputStateFile = "{$userConfiguration['outputPath']}/../state.json";
        $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();
        file_put_contents($outputStateFile, $jsonEncode->encode(
            [
                'lastDownloadedFileTimestamp' => $this->lastDownloadedFileTimestamp,
                'processedFilesInLastTimestampSecond' => $this->processedFilesInLastTimestampSecond,
            ],
            JsonEncoder::FORMAT
        ));
    }
}
