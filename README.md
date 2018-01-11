# Pigeon
KBC Docker app provisions email mailboxes and monitors them for incoming csv files in attachments which are imported to Keboola Storage.

## Status

[![Build Status](https://travis-ci.org/keboola/pigeon.svg)](https://travis-ci.org/keboola/pigeon) [![Code Climate](https://codeclimate.com/github/keboola/pigeon/badges/gpa.svg)](https://codeclimate.com/github/keboola/pigeon)

## Notice

The extractor saves timestamp of last processed email to know where to start in the next run. Potentially, it may bring a problem in a moment when two emails are delivered in the same second and the extractor processes only one of them and ends. Then, in its next run, it will skip the other email and won't process it at all.

## Setup
1. Create CloudFormation stack: `aws cloudformation create-stack --stack-name pigeon --template-body file://./cf-stack.json --parameters ParameterKey=KeboolaStack,ParameterValue=pigeon --region eu-west-1 --capabilities CAPABILITY_NAMED_IAM`
    - It creates S3 Bucket, Dynamo DB table and IAM user
    - You need to set stack name (e.g. `pigeon`), a value for tag `KeboolaStack` (e.g. `pigeon`) and a region
    - Beware that only some regions support Amazon SES
2. Add a MX record with value e.g. `1 inbound-smtp.eu-west-1.amazonaws.com` pointing to your email domain (e.g. `import.keboola.com`) in Route53
3. Verify the domain in SES (https://console.aws.amazon.com/ses/home?region=eu-west-1#verified-senders-domain:)
4. Create a Rule Set in SES if there is none active yet (https://eu-west-1.console.aws.amazon.com/ses/home?region=eu-west-1#receipt-rules: - notice that there can be only one active at a time)
5. Create a Rule in the Rule Set
    - set `Recipient` as `*@email_domain` (e.g. `*@import.test.keboola.com`)
    - add `S3` action, choose the bucket created by CloudFormation and set `incoming/` as Object key prefix
6. Set these `image_parameters`:
    - `access_key_id` - Set from CloudFormation output `UserAccessKey`
    - `#secret_access_key` - Set from CloudFormation output `UserSecretKey` amd encrypt
    - `region` - e.g. `eu-west-1`
    - `bucket` - Set from CloudFormation output `S3BucketName`
    - `dynamo_table` - Set from CloudFormation output `DynamoTable`
    - `rule_set` - SES rule set, `default-rule-set` by default
    - `email_domain` - A domain for mailboxes (e.g. `import.test.keboola.com`)