<?php

defined('ACCESS_KEY_ID')
|| define('ACCESS_KEY_ID', getenv('ACCESS_KEY_ID') ? getenv('ACCESS_KEY_ID') : null);

defined('SECRET_ACCESS_KEY')
|| define('SECRET_ACCESS_KEY', getenv('SECRET_ACCESS_KEY') ? getenv('SECRET_ACCESS_KEY') : null);

defined('REGION')
|| define('REGION', getenv('REGION') ? getenv('REGION') : null);

defined('BUCKET')
|| define('BUCKET', getenv('BUCKET') ? getenv('BUCKET') : null);

defined('EMAIL_DOMAIN')
|| define('EMAIL_DOMAIN', getenv('EMAIL_DOMAIN') ? getenv('EMAIL_DOMAIN') : null);

defined('RULE_SET')
|| define('RULE_SET', getenv('RULE_SET') ? getenv('RULE_SET') : null);

defined('DYNAMO_TABLE')
|| define('DYNAMO_TABLE', getenv('DYNAMO_TABLE') ? getenv('DYNAMO_TABLE') : null);

defined('KBC_PROJECT')
|| define('KBC_PROJECT', getenv('KBC_PROJECT') ? getenv('KBC_PROJECT') : null);

defined('STACK_NAME')
|| define('STACK_NAME', getenv('STACK_NAME') ? getenv('STACK_NAME') : null);

require_once __DIR__ . '/../vendor/autoload.php';
