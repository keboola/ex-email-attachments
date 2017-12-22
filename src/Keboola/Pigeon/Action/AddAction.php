<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Action;

class AddAction extends AbstractAction
{
    public function execute($userConfiguration)
    {
        $emailId = uniqid();
        $email = sprintf(
            '%s-%s@%s',
            $userConfiguration['kbcProject'],
            $emailId,
            $this->appConfiguration['emailDomain']
        );
        $this->createSesRule($userConfiguration['kbcProject'], $emailId, $email);
        $this->addDbRecord($userConfiguration['kbcProject'], $email);
        return ['email' => $email];
    }

    protected function createSesRule($kbcProject, $emailId, $email)
    {
        $ses = $this->initSes();
        $ses->createReceiptRule([
            'Rule' => [
                'Name' => "{$this->appConfiguration['stackName']}-$kbcProject-$emailId",
                'Enabled' => true,
                'Actions' => [
                    [
                        'S3Action' => [
                            'BucketName' => $this->appConfiguration['bucket'],
                            'ObjectKeyPrefix' => "$kbcProject/$emailId/",
                        ],
                    ],
                ],
                'Recipients' => [$email],
            ],
            'RuleSetName' => $this->appConfiguration['ruleSet'],
        ]);
    }

    protected function addDbRecord($kbcProject, $email)
    {
        $dynamo = $this->initDynamoDb();
        $dynamo->putItem([
            'TableName' => $this->appConfiguration['dynamoTable'],
            'Item' => [
                'Project' => ['N' => $kbcProject],
                'Email' => ['S' => $email],
            ],
        ]);
    }
}
