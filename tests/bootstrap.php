<?php

defined('USER_ACCESS_KEY_ID')
|| define('USER_ACCESS_KEY_ID', getenv('USER_ACCESS_KEY_ID') ? getenv('USER_ACCESS_KEY_ID') : null);

defined('USER_SECRET_ACCESS_KEY')
|| define('USER_SECRET_ACCESS_KEY', getenv('USER_SECRET_ACCESS_KEY') ? getenv('USER_SECRET_ACCESS_KEY') : null);

defined('REGION')
|| define('REGION', getenv('REGION') ? getenv('REGION') : null);

defined('S3_BUCKET')
|| define('S3_BUCKET', getenv('S3_BUCKET') ? getenv('S3_BUCKET') : null);

defined('EMAIL_DOMAIN')
|| define('EMAIL_DOMAIN', getenv('EMAIL_DOMAIN') ? getenv('EMAIL_DOMAIN') : null);

defined('DYNAMO_TABLE')
|| define('DYNAMO_TABLE', getenv('DYNAMO_TABLE') ? getenv('DYNAMO_TABLE') : null);

defined('STACK_NAME')
|| define('STACK_NAME', getenv('STACK_NAME') ? getenv('STACK_NAME') : null);

require_once __DIR__ . '/../vendor/autoload.php';
