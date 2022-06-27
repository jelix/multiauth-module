#!/bin/bash

if [ "$1" == "" -o "$2" == "" ]; then
  echo "Usage: ssl.sh <command> <domain>"
  echo "commands: reset, resetCA, createCA, createCert, checkCSR, checkCert"
fi

OPENSSL_IMAGE="jelix-multiauth-openssl"

docker image inspect $OPENSSL_IMAGE >/dev/null 2>&1
if [ "$?" == "1" ]; then
  docker build -t $OPENSSL_IMAGE openssl/
fi

if [ "$1" == "createCA" ]; then
  docker run -it -v $(pwd)/certs:/sslcerts --user $(id -u):$(id -g) --env CA_CERT_DOMAIN=$2  $OPENSSL_IMAGE $1
else
  if [ "$3" == "" ]; then
    cadomain=tests.jelix
  else
    cadomain=$3
  fi
  docker run -it -v $(pwd)/certs:/sslcerts --user $(id -u):$(id -g) --env CERT_DOMAIN=$2  --env CA_CERT_DOMAIN=$cadomain $OPENSSL_IMAGE $1
fi
