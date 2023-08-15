#!/usr/bin/env bash

set +o errexit
set -o nounset
set -o pipefail

TEST_IMAGES=(
  "php:7.4.33-cli-buster"
  "php:8.0.30-cli-buster"
  "php:8.1.22-cli-bookworm"
  "php:8.2.8-cli-bookworm"
  "php:8.3.0beta2-cli-bookworm"
)

FAILED=()

for IMAGE in ${TEST_IMAGES[@]}
do
  echo
  echo Running $IMAGE
  echo
  docker run --rm --mount type=bind,source="$(pwd)",target="/code" -w /code $IMAGE /code/vendor/phpunit/phpunit/phpunit
  RESULT=$?
  if [[ $RESULT != 0 ]]
  then
    echo $RESULT
    echo $IMAGE failed
    FAILED+=("$IMAGE")
  fi
done

if [[ ${FAILED[@]} ]]
then
  echo
  echo Some test runs failed.
  echo
  echo Images that caused failed runs:
  echo -------------------------------
  for IMAGE in ${FAILED[@]}
  do
    echo $IMAGE
  done
else
  echo
  echo All test runs passed
fi
