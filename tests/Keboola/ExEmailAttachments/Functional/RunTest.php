<?php

namespace Keboola\ExEmailAttachments\Tests\Functional;

use Keboola\ExEmailAttachments\App;
use Symfony\Component\Console\Output\NullOutput;

class RunTest extends AbstractTest
{
    public function testRunOk()
    {
        $id = uniqid();
        $config = uniqid();
        $email = "{$this->project}-{$config}-{$id}@" . EMAIL_DOMAIN;

        $emailBody = str_replace('{{EMAIL}}', $email, file_get_contents(__DIR__ . '/../email'));
        $this->s3->putObject([
            'Bucket' => S3_BUCKET,
            'Key' => "{$this->project}/{$config}/{$id}0",
            'Body' => $emailBody,
        ]);
        sleep(2);
        $this->s3->putObject([
            'Bucket' => S3_BUCKET,
            'Key' => "{$this->project}/{$config}/{$id}",
            'Body' => $emailBody,
        ]);
        $object = $this->s3->headObject([
            'Bucket' => S3_BUCKET,
            'Key' => "{$this->project}/{$config}/{$id}",
        ]);
        $lastDownloadedFileTimestamp = $object['LastModified']->format('U') - 1;
        $this->dynamo->putItem([
            'TableName' => DYNAMO_TABLE,
            'Item' => [
                'Project' => ['N' => $this->project],
                'Config' => ['S' => $config],
                'Email' => ['S' => $email],
            ],
        ]);

        App::execute(
            $this->appConfiguration,
            [
                'action' => 'run',
                'config' => $config,
                'kbcProject' => $this->project,
                'outputPath' => $this->outputPath,
                'email' => $email,
                'incremental' => true,
                'enclosure' => '"',
                'delimiter' => ',',
                'primaryKey' => ['id'],
                'state' => ['lastDownloadedFileTimestamp' => $lastDownloadedFileTimestamp],
            ],
            $this->temp,
            new NullOutput()
        );
        $dataFolder = '/data/out/tables';
        $this->assertDirectoryExists($dataFolder);

        $this->assertFileExists("$dataFolder/data.csv.manifest");
        $manifest = json_decode(file_get_contents("$dataFolder/data.csv.manifest"), true);
        $this->assertArrayHasKey('incremental', $manifest);
        $this->assertArrayHasKey('enclosure', $manifest);
        $this->assertArrayHasKey('delimiter', $manifest);
        $this->assertEquals(true, $manifest['incremental']);
        $this->assertEquals('"', $manifest['enclosure']);
        $this->assertEquals(',', $manifest['delimiter']);

        $this->assertFileExists("$dataFolder/data.csv");
        $file = file("$dataFolder/data.csv");
        $this->assertCount(6, $file);
        $this->assertEquals('"id","name","order"', trim($file[0]));
    }
}
