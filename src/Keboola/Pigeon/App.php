<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon;

use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Keboola\Temp\Temp;
use PhpMimeMailParser\Parser;

class App
{
    /** @var Temp  */
    protected $temp;
    /** @var SesClient  */
    protected $ses;
    /** @var S3Client  */
    protected $s3;
    protected $emailDomain;
    protected $bucketName;
    protected $ruleSetName;

    public function __construct($appConfiguration, Temp $temp)
    {
        $this->temp = $temp;
        $this->ses = new SesClient([
            'credentials'=> [
                'key' => $appConfiguration['accessKeyId'],
                'secret' => $appConfiguration['secretAccessKey'],
            ],
            'region' => $appConfiguration['region'],
            'version' => '2010-12-01',
        ]);
        $this->s3 = new S3Client([
            'credentials'=> [
                'key' => $appConfiguration['accessKeyId'],
                'secret' => $appConfiguration['secretAccessKey'],
            ],
            'region' => $appConfiguration['region'],
            'version' => '2006-03-01',
        ]);
        $this->emailDomain = $appConfiguration['emailDomain'];
        $this->bucketName = $appConfiguration['bucket'];
        $this->ruleSetName = $appConfiguration['ruleSet'];
    }

    public function run($userConfiguration)
    {
        switch ($userConfiguration['action']) {
            case 'run':
                $this->runAction($userConfiguration);
                break;
            case 'add':
                return $this->addAction($userConfiguration);
                break;
            default:
                throw new Exception("Action {$userConfiguration['action']} is not supported");
        }
    }

    public function runAction($userConfiguration)
    {
        $this->temp->initRunFolder();
        $objects = $this->s3->listObjectsV2([
            'Bucket' => $this->bucketName,
            'Prefix' => "{$userConfiguration['kbcProject']}/{$userConfiguration['id']}/",
        ]);
        $parser = new Parser();
        foreach ($objects['Contents'] as $file) {
            if ($file['Key'] != "{$userConfiguration['kbcProject']}/{$userConfiguration['id']}/AMAZON_SES_SETUP_NOTIFICATION") {
                $tempFile = $this->temp->createTmpFile()->getRealPath();
                $this->s3->getObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $file['Key'],
                    'SaveAs' => $tempFile,
                ]);
                $parser->setPath($tempFile);
                $parser->saveAttachments($this->temp->getTmpFolder() . '/');
                $attachments = $parser->getAttachments();
                if (count($attachments) > 0) {
                    foreach ($attachments as $attachment) {
                        echo file_get_contents($this->temp->getTmpFolder() . '/' . $attachment->getFilename());
                        //@TODO
                    }
                }
            }
        }
    }

    public function addAction($userConfiguration)
    {
        $id = uniqid();
        $email = sprintf('%s-%s@%s', $userConfiguration['kbcProject'], $id, $this->emailDomain);
        $this->ses->createReceiptRule([
            'Rule' => [
                'Name' => "pigeon-{$userConfiguration['kbcProject']}-$id",
                'Enabled' => true,
                'Actions' => [
                    [
                        'S3Action' => [
                            'BucketName' => $this->bucketName,
                            'ObjectKeyPrefix' => "{$userConfiguration['kbcProject']}/$id/",
                        ],
                    ],
                ],
                'Recipients' => [$email],
            ],
            'RuleSetName' => $this->ruleSetName,
        ]);
        return ['email' => $email];
    }
}
