<?php

namespace Keboola\ExEmailAttachments\Action;

use Aws\Api\DateTimeResult;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Keboola\ExEmailAttachments\Exception\EmailException;
use Keboola\ExEmailAttachments\Exception\InvalidEmailRecipientException;
use Keboola\ExEmailAttachments\Exception\MoreAttachmentsInEmailException;
use Keboola\ExEmailAttachments\Exception\NoAttachmentInEmailException;
use PhpMimeMailParser\Parser;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class RunAction extends AbstractAction
{
    /** @var S3Client */
    protected $s3Client;
    protected $lastDownloadedFileTimestamp;
    protected $processedFilesInLastTimestampSecond;

    public function execute(array $userConfiguration)
    {
        $dynamo = $this->initDynamoDb();
        $emailRecipient = $this->getDbRecord($dynamo, $userConfiguration['kbcProject'], $userConfiguration['config']);

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

        $csvFiles = [];
        foreach ($filesToDownload as $fileToDownload) {
            try {
                $csvFiles[] = $this->downloadAttachmentFromS3File($fileToDownload['key'], $emailRecipient);
                $this->updateState($fileToDownload['key'], $fileToDownload['timestamp']);
            } catch (EmailException $e) {
                $this->consoleOutput->writeln($e->getMessage());
            }
        }

        $this->saveFiles($userConfiguration, $csvFiles);
        $this->saveState($userConfiguration);
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

    public function downloadAttachmentFromS3File(string $fileKey, string $emailRecipient) : string
    {
        $tempFile = $this->downloadS3File($fileKey);
        $parser = $this->parseEmailFromS3File($tempFile, $emailRecipient);
        return $this->getTextAttachment($parser);
    }

    public function downloadS3File(string $fileKey) : string
    {
        $tempFile = $this->temp->createTmpFile()->getRealPath();
        $this->s3Client->getObject([
            'Bucket' => $this->appConfiguration['bucket'],
            'Key' => $fileKey,
            'SaveAs' => $tempFile,
        ]);
        return $tempFile;
    }

    public function getAddressesFromEmailField(string $field) : array
    {
        $result = [];
        foreach (mailparse_rfc822_parse_addresses($field) as $item) {
            $result[] = trim($item['address']);
        }
        return $result;
    }

    public function checkEmailInRecipients(array $fields, string $email) : bool
    {
        foreach ($fields as $field) {
            if (in_array($email, $this->getAddressesFromEmailField($field))) {
                return true;
            }
        }
        return false;
    }

    public function parseEmailFromS3File(string $tempFile, string $emailRecipient) : Parser
    {
        $parser = new Parser();
        $parser->setPath($tempFile);
        $parser->saveAttachments($this->temp->getTmpFolder() . '/');
        if (!$this->checkEmailInRecipients([
            $parser->getHeader('to'),
            $parser->getHeader('cc'),
            $parser->getHeader('bcc'),
        ], $emailRecipient)) {
            throw new InvalidEmailRecipientException($this->getFromAndDateClause($parser) . ' has invalid recipient.');
        }

        return $parser;
    }

    public function getFromAndDateClause(Parser $parser) : string
    {
        $date = $parser->getHeader('Date');
        $from = $this->getAddressesFromEmailField($parser->getHeader('From'))[0];
        return "Email sent by $from received on $date";
    }

    public function getTextAttachment(Parser $parser) : string
    {
        $result = null;
        $attachments = $parser->getAttachments();
        foreach ($attachments as $attachment) {
            $file = "{$this->temp->getTmpFolder()}/{$attachment->getFilename()}";
            if (substr(mime_content_type($file), 0, 5) === 'text/') {
                if ($result) {
                    throw new MoreAttachmentsInEmailException($this->getFromAndDateClause($parser) . ' has more than one text attachment.');
                }
                $result = $file;
            }
        }
        if (!$result) {
            throw new NoAttachmentInEmailException($this->getFromAndDateClause($parser) . ' has no text attachment.');
        }

        $this->consoleOutput->writeln($this->getFromAndDateClause($parser) . ' was processed and saved a csv file with size ' . filesize($result));
        return $result;
    }

    public function saveFiles(array $userConfiguration, array $files) : void
    {
        if (!count($files)) {
            $this->consoleOutput->writeln('No emails processed.');
            return;
        }

        foreach ($files as $i => $file) {
            $counter = $i > 0 ? $i : '';
            $newFileName = "{$userConfiguration['outputPath']}/data{$counter}.csv";
            rename($file, $newFileName);
            $this->saveManifest($newFileName, $userConfiguration);
        }
    }

    public function saveManifest(string $filename, array $userConfiguration) : void
    {
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
        file_put_contents("{$filename}.manifest", json_encode($manifest));
    }

    protected function readState(array $userConfiguration) : void
    {
        $this->lastDownloadedFileTimestamp = isset($userConfiguration['state']['lastDownloadedFileTimestamp'])
            ? $userConfiguration['state']['lastDownloadedFileTimestamp'] : 0;
        $this->processedFilesInLastTimestampSecond = isset($userConfiguration['state']['processedFilesInLastTimestampSecond'])
            ? $userConfiguration['state']['processedFilesInLastTimestampSecond'] : [];
    }

    protected function updateState($fileKey, $timestamp) : void
    {
        if ($this->lastDownloadedFileTimestamp != $timestamp) {
            $this->processedFilesInLastTimestampSecond = [];
        }
        $this->lastDownloadedFileTimestamp = max($this->lastDownloadedFileTimestamp, $timestamp);
        $this->processedFilesInLastTimestampSecond[] = $fileKey;
    }

    protected function saveState(array $userConfiguration) : void
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
