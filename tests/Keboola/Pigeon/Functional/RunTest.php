<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

use Keboola\Pigeon\Exception;

class RunTest extends AbstractTest
{
    public function testRunInvalidEmail()
    {
        $this->expectException(Exception::class);
        $this->app->run(['action' => 'run', 'kbcProject' => $this->project, 'email' => uniqid()]);
    }

    public function testRunOk()
    {
        $id = uniqid();
        $email = "{$this->project}-{$id}@" . EMAIL_DOMAIN;
        $this->s3->putObject([
            'Bucket' => BUCKET,
            'Key' => "{$this->project}/$id/$id",
            'SourceFile' => __DIR__ . '/email',
        ]);
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Email' => ['S' => $email],
            ],
        ]);

        $result = $this->app->run(['action' => 'run', 'kbcProject' => $this->project, 'email' => $email]);
        $this->assertArrayHasKey('processedAttachments', $result);
        $this->assertEquals(1, $result['processedAttachments']);
    }
}
