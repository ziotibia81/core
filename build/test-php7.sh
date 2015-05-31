#!/bin/bash

DOCKER_CONTAINER_ID=$(docker run -d deepdiver/php7)
echo "$DOCKER_CONTAINER_ID"
docker exec "$DOCKER_CONTAINER_ID" git clone https://github.com/owncloud/core.git /home
docker exec "$DOCKER_CONTAINER_ID" /bin/sh -c 'cd /home; git submodule update --init'

docker exec "$DOCKER_CONTAINER_ID" /bin/sh -c 'more /usr/local/php/conf.d/owncloud.ini'
docker exec "$DOCKER_CONTAINER_ID" /bin/sh -c 'export PATH=$PATH:/usr/local/php/bin; php --info'

docker exec "$DOCKER_CONTAINER_ID" /bin/sh -c 'mkdir -p /home/data'
docker exec "$DOCKER_CONTAINER_ID" /bin/sh -c 'export PATH=$PATH:/usr/local/php/bin; php --version; cd /home; ./autotest.sh sqlite'

docker kill "$DOCKER_CONTAINER_ID"
