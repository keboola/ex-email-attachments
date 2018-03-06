<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments\Action;

class ListAction extends AbstractAction
{
    public function execute($userConfiguration)
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
