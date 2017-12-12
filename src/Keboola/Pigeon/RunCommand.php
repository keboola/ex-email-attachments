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

            $validatedConfig = $this->validateInput($config);
            $validatedConfig['outputPath'] = $outputPath;
            $validatedConfig['output'] = $consoleOutput;
            if (!empty($config['parameters']['bucket'])) {
                $validatedConfig['bucket'] = $config['parameters']['bucket'];
            }



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

    public function validateInput($config)
    {

    }
}
