<?php
/**
 * @package ex-email-attachments
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

require __DIR__ . '/bootstrap.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

$application = new Application;
$application->add(new \Keboola\ExEmailAttachments\RunCommand);
$application->run(null, new ConsoleOutput());
