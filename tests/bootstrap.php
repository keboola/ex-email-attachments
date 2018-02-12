<?php

defined('AWS_ACCESS_KEY_ID')
|| define('AWS_ACCESS_KEY_ID', getenv('AWS_ACCESS_KEY_ID') ? getenv('AWS_ACCESS_KEY_ID') : null);

defined('AWS_SECRET_ACCESS_KEY')
|| define('AWS_SECRET_ACCESS_KEY', getenv('AWS_SECRET_ACCESS_KEY') ? getenv('AWS_SECRET_ACCESS_KEY') : null);

defined('DYNAMO_TABLE')
|| define('DYNAMO_TABLE', getenv('DYNAMO_TABLE') ? getenv('DYNAMO_TABLE') : null);

defined('EMAIL_DOMAIN')
|| define('EMAIL_DOMAIN', getenv('EMAIL_DOMAIN') ? getenv('EMAIL_DOMAIN') : null);

defined('REGION')
|| define('REGION', getenv('REGION') ? getenv('REGION') : null);

defined('S3_BUCKET')
|| define('S3_BUCKET', getenv('S3_BUCKET') ? getenv('S3_BUCKET') : null);

require_once __DIR__ . '/../vendor/autoload.php';
