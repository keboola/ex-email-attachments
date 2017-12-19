<?php
/**
 * @package pigeon
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\Pigeon;

use Keboola\Temp\Temp;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
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

            $app = new App($appConfiguration, new Temp());
            $result = $app->run($userConfiguration);
            $jsonEncode = new JsonEncode();
            $consoleOutput->writeln(is_array($result)
                ? $jsonEncode->encode($result, JsonEncoder::FORMAT) : $result);

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
        $required = ['access_key_id', '#secret_access_key', 'region', 'bucket', 'email_domain', 'rule_set',
            'dynamo_table'];
        foreach ($required as $input) {
            if (!isset($config['image_parameters'][$input])) {
                throw new \Exception("$input is missing from image parameters");
            }
        }
        return [
            'accessKeyId' => $config['image_parameters']['access_key_id'],
            'secretAccessKey' => $config['image_parameters']['#secret_access_key'],
            'region' => $config['image_parameters']['region'],
            'bucket' => $config['image_parameters']['bucket'],
            'emailDomain' => $config['image_parameters']['email_domain'],
            'ruleSet' => $config['image_parameters']['rule_set'],
            'dynamoTable' => $config['image_parameters']['dynamo_table'],
        ];
    }

    public function validateUserConfiguration($config)
    {
        $result = [
            'kbcProject' => getenv('KBC_PROJECTID'),
            'action' => isset($config['action']) ? $config['action'] : 'run',
            'id' => $config['parameters']['id'] //@TODO
        ];
        if ($result['action'] == 'run') {
            /*if (!isset($config['storage']) || !isset($config['storage']['output'])
                || !isset($config['storage']['output']['tables']) || !count($config['storage']['output']['tables'])) {
                throw new Exception('There is no table in output mapping configured');
            }*/
        }
        return $result;
    }
}
