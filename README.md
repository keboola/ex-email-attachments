# Email attachments extractor
KBC Docker app provisions email mailboxes and monitors them for incoming csv files in attachments which are imported to Keboola Storage.

Lambda handler is in separate repository [keboola/ex-email-attachments-lambda](https://github.com/keboola/ex-email-attachments-lambda)

## Status

[![Build Status](https://travis-ci.org/keboola/ex-email-attachments.svg)](https://travis-ci.org/keboola/ex-email-attachments) [![Code Climate](https://codeclimate.com/github/keboola/ex-email-attachments/badges/gpa.svg)](https://codeclimate.com/github/keboola/ex-email-attachments)

## App Flow

- Emails are processed by AWS SES service
- `get` sync action generates an email address for extractor's configuration and saves it to DynamoDB table
- SES has a rule to save all emails with specified email domain to a S3 bucket in folder `_incoming`
- There is a lambda handler subscribed to the `_incoming` folder which gets recipient address and checks its existence in DynamoDB
- If the email exists, the email file is moved to folder `[projectId]/[configId]`
- If the email does not exist, the file is moved to folder `_invalid`
- The S3 bucket has a lifecycle 30 days - all files are deleted after that period
- The extractor checks S3 folder `[projectId]/[configId]` and processes new files
- It saves timestamp of last processed file to the state

## Notice

The extractor saves timestamp of last processed email to know where to start in the next run. Potentially, it may bring a problem in a moment when two emails are delivered in the same second and the extractor processes only one of them and ends. Then, in its next run, it will skip the other email and won't process it at all.

## Setup
1. Create CloudFormation stack: `aws cloudformation create-stack --stack-name dev-ex-email-attachments --template-body file://./cf-stack.json --parameters ParameterKey=KeboolaStack,ParameterValue=ex-email-attachments --region eu-west-1 --capabilities CAPABILITY_NAMED_IAM`
    - It creates S3 Bucket, Dynamo DB table and IAM user
    - You need to set stack name (e.g. `dev-ex-email-attachments`), a value for tag `KeboolaStack` (e.g. `ex-email-attachments`) and a region
    - Beware that only some regions support Amazon SES
2. Add a MX record with value e.g. `1 inbound-smtp.eu-west-1.amazonaws.com` pointing to your email domain (e.g. `import.keboola.com`) in Route53
3. Verify the domain in SES (https://console.aws.amazon.com/ses/home?region=eu-west-1#verified-senders-domain:)
4. Create a Rule Set in SES if there is none active yet (https://eu-west-1.console.aws.amazon.com/ses/home?region=eu-west-1#receipt-rules: - notice that there can be only one active at a time)
5. Create a Rule in the Rule Set
    - set `Recipient` as `email_domain` (e.g. `import.test.keboola.com`)
    - add `S3` action, choose the bucket created by CloudFormation and set `_incoming/` as Object key prefix
6. Set these `image_parameters`:
    - `access_key_id` - Set from CloudFormation output `UserAccessKey`
    - `#secret_access_key` - Set from CloudFormation output `UserSecretKey` amd encrypt
    - `region` - e.g. `eu-west-1`
    - `bucket` - Set from CloudFormation output `S3BucketName`
    - `dynamo_table` - Set from CloudFormation output `DynamoTable`
    - `rule_set` - SES rule set, `default-rule-set` by default
    - `email_domain` - A domain for mailboxes (e.g. `import.test.keboola.com`)
