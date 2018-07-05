<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments;

use Keboola\ExEmailAttachments\Exception\Exception;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Output\OutputInterface;

class App
{
    public static function execute($appConfiguration, $userConfiguration, Temp $temp, OutputInterface $consoleOutput)
    {
        switch ($userConfiguration['action']) {
            case 'run':
                $action = new \Keboola\ExEmailAttachments\Action\RunAction($appConfiguration, $temp, $consoleOutput);
                return $action->execute($userConfiguration);
                break;
            case 'get':
                $action = new \Keboola\ExEmailAttachments\Action\GetAction($appConfiguration, $temp, $consoleOutput);
                return $action->execute($userConfiguration);
                break;
            case 'list':
                $action = new \Keboola\ExEmailAttachments\Action\ListAction($appConfiguration, $temp, $consoleOutput);
                return $action->execute($userConfiguration);
                break;
            default:
                throw new Exception("Action {$userConfiguration['action']} is not supported");
        }
    }
}
