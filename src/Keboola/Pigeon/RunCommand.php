<?php
/**
 * @package pigeon
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\Pigeon;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    protected function configure()
    {
        $this->setName('run');
        $this->setDescription('Runs Extractor');
        $this->addArgument('data directory', InputArgument::REQUIRED, 'Data directory');
    }

    protected function execute(InputInterface $input, OutputInterface $consoleOutput)
    {
        $dataDirectory = $input->getArgument('data directory');

        $configFile = "$dataDirectory/config.json";
        if (!file_exists($configFile)) {
            throw new \Exception("Config file not found at path $configFile");
        }
        $jsonDecode = new JsonDecode(true);
        $config = $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);

        try {
            $outputPath = "$dataDirectory/out/tables";
            (new Filesystem())->mkdir([$outputPath]);

            $appConfiguration = $this->validateAppConfiguration($config);
            $appConfiguration['output'] = $consoleOutput;

            $userConfiguration = $this->validateUserConfiguration($config);
            $userConfiguration['outputPath'] = $outputPath;

            $app = new App($appConfiguration);
            $app->run($userConfiguration);

            return 0;
        } catch (Exception $e) {
            $consoleOutput->writeln($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            if ($consoleOutput instanceof ConsoleOutput) {
                $consoleOutput->getErrorOutput()->writeln("{$e->getMessage()}\n{$e->getTraceAsString()}");
            } else {
                $consoleOutput->writeln("{$e->getMessage()}\n{$e->getTraceAsString()}");
            }
            return 2;
        }
    }

    public function validateAppConfiguration($config)
    {
        if (!isset($config['image_parameters']['access_key_id'])) {
            throw new \Exception('Access key id is missing from image parameters');
        }
        if (!isset($config['image_parameters']['#secret_access_key'])) {
            throw new \Exception('Secret access key is missing from image parameters');
        }
        if (!isset($config['image_parameters']['region'])) {
            throw new \Exception('Region is missing from image parameters');
        }
        if (!isset($config['image_parameters']['email_domain'])) {
            throw new \Exception('Email domain is missing from image parameters');
        }
        return [
            'accessKeyId' => $config['image_parameters']['access_key_id'],
            'secretAccessKey' => $config['image_parameters']['#secret_access_key'],
            'region' => $config['image_parameters']['region'],
            'emailDomain' => $config['image_parameters']['email_domain'],
        ];
    }

    public function validateUserConfiguration($config)
    {
        $result = [];
        $result['action'] = isset($config['action']) ? $config['action'] : 'run';
        if (!isset($config['storage']) || !isset($config['storage']['output'])
            || !isset($config['storage']['output']['tables']) || !count($config['storage']['output']['tables'])) {
            throw new Exception('There is no table in output mapping cofnigured');
        }
        return $result;
    }
}
