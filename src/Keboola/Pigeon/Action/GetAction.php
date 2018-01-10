<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Action;

use Keboola\Pigeon\Exception;

class GetAction extends AbstractAction
{
    public function execute($userConfiguration)
    {
        $dynamo = $this->initDynamoDb();
        try {
            $email = $this->getDbRecord($dynamo, $userConfiguration['kbcProject'], $userConfiguration['config']);
        } catch (Exception $e) {
            $emailId = uniqid();
            $email = sprintf(
                '%s-%s-%s@%s',
                $userConfiguration['kbcProject'],
                $userConfiguration['config'],
                $emailId,
                $this->appConfiguration['emailDomain']
            );
            $dynamo->putItem([
                'TableName' => $this->appConfiguration['dynamoTable'],
                'Item' => [
                    'Project' => ['N' => $userConfiguration['kbcProject']],
                    'Config' => ['S' => $userConfiguration['config']],
                    'Email' => ['S' => $email],
                ],
            ]);
        }

        return ['email' => $email];
    }
}
