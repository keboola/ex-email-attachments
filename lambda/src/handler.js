'use strict';

const _ = require('lodash');
const { UserError, RequestHandler } = require('@keboola/serverless-request-handler');
const aws = require('aws-sdk');
const moment = require('moment');
const Promise = require('bluebird');
const simpleParser = require('mailparser').simpleParser;

aws.config.setPromisesDependency(Promise);

module.exports.handler = (event, context, callback) => RequestHandler.handler(() => {
  if (!_.has(event, 'Records') || !event.Records.length ||
    !_.has(event.Records[0], 's3') || !_.has(event.Records[0].s3, 'bucket') ||
    !_.has(event.Records[0].s3, 'object') ||
    !_.has(event.Records[0].s3.bucket, 'name') ||
    !_.has(event.Records[0].s3.object, 'key')) {
    throw Error(`Event is missing. See: ${JSON.stringify(event)}`);
  }
  const bucket = event.Records[0].s3.bucket.name;
  const sourceKey = event.Records[0].s3.object.key;
  const path = sourceKey.split('/');

  if (event.Records[0].eventName !== 'ObjectCreated:Put' || path[0] !== '_incoming') {
    return callback();
  }

  const s3 = new aws.S3();
  const dynamo = new aws.DynamoDB({ region: process.env.REGION });

  // 1) Read the mail from s3
  const promise = s3.getObject({ Bucket: bucket, Key: sourceKey }).promise()
    .catch((err) => {
      if (err.code === 'NotFound' || err.code === 'Forbidden') {
        throw UserError.notFound(`Uploaded file ${sourceKey} was not found in s3`);
      }
      throw err;
    })
    // 2) Parse destination address from the file and check its existence in Dynamo
    .then(data => simpleParser(data.Body)
      .then(mail => new Promise(resolve => resolve(mail.to.text.split('-')))
        .then(emailParts => new Promise((resolve, reject) => {
          if (_.size(emailParts) < 3) {
            reject(UserError.unprocessable());
          }
          resolve();
        })
          .then(() => dynamo.getItem({
            Key: {
              Project: { N: emailParts[0] },
              Config: { S: emailParts[1] },
            },
            TableName: process.env.DYNAMO_TABLE,
          }).promise())
          .then((res) => {
            if (!_.has(res, 'Item.Email.S')) {
              throw UserError.unprocessable();
            }
            if (res.Item.Email.S !== mail.to.text) {
              throw UserError.unprocessable();
            }
          })
          .then(() => moveFile(s3, bucket, sourceKey, `${emailParts[0]}/${emailParts[1]}/${getFileName(mail)}`))
        )
        .catch((err) => {
          if (err instanceof UserError && err.code === 422) {
            return moveFile(s3, bucket, `_incoming/${path[1]}`, `_invalid/${getFileName(mail)}`);
          }
          throw err;
        })
      )
    );

  return RequestHandler.responsePromise(promise, event, context, callback);
}, event, context, callback);

const getFileName = mail => `${mail.to.text}-${moment().toISOString()}`;

const moveFile = (s3, bucket, from, to) => {
  return s3.copyObject({
    CopySource: `${bucket}/${from}`,
    Bucket: bucket,
    Key: to,
  }).promise()
  .then(() => s3.deleteObject({ Bucket: bucket, Key: from }).promise());
};
