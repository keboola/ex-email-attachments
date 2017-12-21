#!/bin/bash
set -e

docker pull quay.io/keboola/developer-portal-cli-v2:latest
export REPOSITORY=`docker run --rm \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-repository ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP}`
echo "REPOSITORY: $REPOSITORY"
docker tag ${KBC_APP_REPOSITORY}:latest ${REPOSITORY}:${TRAVIS_TAG}
docker tag ${KBC_APP_REPOSITORY}:latest ${REPOSITORY}:latest
echo "STEP 2"
eval $(docker run --rm \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-login ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP})
echo "STEP 3"
docker push ${REPOSITORY}:${TRAVIS_TAG}
echo "STEP 4"
docker push ${REPOSITORY}:latest
echo "STEP 5"

# Deploy to KBC -> update the tag in Keboola Developer Portal (needs $KBC_DEVELOPERPORTAL_VENDOR & $KBC_DEVELOPERPORTAL_APP)
docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest update-app-repository ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP} ${TRAVIS_TAG} \
    ecr ${REPOSITORY}