<?php
/**
 * @package ex-email-attachments
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\ExEmailAttachments;

use Keboola\Temp\Temp;

class App
{
    public static function execute($appConfiguration, $userConfiguration, Temp $temp)
    {
        switch ($userConfiguration['action']) {
            case 'run':
                $action = new \Keboola\ExEmailAttachments\Action\RunAction($appConfiguration, $temp);
                return $action->execute($userConfiguration);
                break;
            case 'get':
                $action = new \Keboola\ExEmailAttachments\Action\GetAction($appConfiguration, $temp);
                return $action->execute($userConfiguration);
                break;
            case 'list':
                $action = new \Keboola\ExEmailAttachments\Action\ListAction($appConfiguration, $temp);
                return $action->execute($userConfiguration);
                break;
            default:
                throw new Exception("Action {$userConfiguration['action']} is not supported");
        }
    }
}
