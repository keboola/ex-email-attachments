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
        $config = $this->getConfig($dataDirectory);

        try {
            $outputPath = "$dataDirectory/out/tables";
            (new Filesystem())->mkdir([$outputPath]);

            $appConfiguration = $this->validateAppConfiguration($config);

            $userConfiguration = $this->validateUserConfiguration($config);
            $userConfiguration['outputPath'] = $outputPath;
            $userConfiguration['state'] = $this->getState($dataDirectory);

            $result = App::execute($appConfiguration, $userConfiguration, new Temp());
            $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();
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

    protected function getConfig($dataDirectory)
    {
        $configFile = "$dataDirectory/config.json";
        if (!file_exists($configFile)) {
            throw new \Exception("Config file not found at path $configFile");
        }
        $jsonDecode = new JsonDecode(true);
        return $jsonDecode->decode(file_get_contents($configFile), JsonEncoder::FORMAT);
    }

    protected function getState($dataDirectory)
    {
        $inputStateFile = "$dataDirectory/in/state.json";
        if (file_exists($inputStateFile)) {
            $jsonDecode = new JsonDecode(true);
            return $jsonDecode->decode(file_get_contents($inputStateFile), JsonEncoder::FORMAT);
        }
        return [];
    }

    public function validateAppConfiguration($config)
    {
        $params = $this->getRequiredParameters(
            $config,
            ['access_key_id', '#secret_access_key', 'region', 'bucket', 'email_domain', 'rule_set',
                'dynamo_table', 'stack_name'],
            'image_parameters'
        );
        return [
            'accessKeyId' => $params['access_key_id'],
            'secretAccessKey' => $params['#secret_access_key'],
            'region' => $params['region'],
            'bucket' => $params['bucket'],
            'emailDomain' => $params['email_domain'],
            'ruleSet' => $params['rule_set'],
            'dynamoTable' => $params['dynamo_table'],
            'stackName' => $params['stack_name'],
        ];
    }

    public function validateUserConfiguration($config)
    {
        $result = [
            'kbcProject' => getenv('KBC_PROJECTID'),
            'action' => isset($config['action']) ? $config['action'] : 'run',
        ];
        if ($result['action'] == 'run') {
            $result = array_merge($result, $this->getRequiredParameters($config, ['email'], 'parameters'));
            $result = array_merge(
                $result,
                $this->getOptionalParameters($config, ['incremental', 'enclosure', 'delimiter'], 'parameters')
            );
        }
        return $result;
    }

    protected function getRequiredParameters($config, $required, $field)
    {
        $result = [];
        foreach ($required as $input) {
            if (!isset($config[$field][$input])) {
                throw new \Exception("$input is missing from $field");
            }
            $result[$input] = $config[$field][$input];
        }
        return $result;
    }

    protected function getOptionalParameters($config, $optional, $field)
    {
        $result = [];
        foreach ($optional as $input) {
            if (isset($config[$field][$input])) {
                $result[$input] = $config[$field][$input];
            }
        }
        return $result;
    }
}
