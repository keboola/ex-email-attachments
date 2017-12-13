<?php
/**
 * @package pigeon
 * @copyright 2017 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\Pigeon;

use Aws\Ses\SesClient;

class App
{
    protected $ses;
    protected $emailDomain;

    public function __construct($appConfiguration)
    {
        $this->ses = new SesClient([
            'credentials'=> [
                'key' => $appConfiguration['accessKeyId'],
                'secret' => $appConfiguration['secretAccessKey'],
            ],
            'region' => $appConfiguration['region'],
        ]);
        $this->emailDomain = $appConfiguration['emailDomain'];
    }

    public function run($userConfiguration)
    {
        switch ($userConfiguration['action']) {
            case 'run':
                return $this->runAction($userConfiguration);
                break;
            case 'add':
                return $this->addAction($userConfiguration);
                break;
            default:
                throw new Exception("Action {$userConfiguration['action']} is not supported");
        }
    }

    public function runAction($userConfiguration)
    {

    }

    public function addAction($userConfiguration)
    {
        $this->ses->create
    }
}
