<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon\Tests\Functional;

class RunTest extends AbstractTest
{
    public function testRun()
    {
        $id = uniqid();
        $this->s3->putObject([
            'Bucket' => BUCKET,
            'Key' => "{$this->project}/$id/$id",
            'SourceFile' => __DIR__ . '/email',
        ]);

        $result = $this->app->run(['action' => 'run', 'kbcProject' => $this->project, 'id' => $id]);
        $this->assertArrayHasKey('processedAttachments', $result);
        $this->assertEquals(1, $result['processedAttachments']);
    }
}
