#!/bin/bash
# set -x

source ${HOME}/.profile

echo "boot started with path ${WORKDIR}"

cd ${WORKDIR}

scomposer install || echo "composer install failed, continuing..."

sleep infinity
