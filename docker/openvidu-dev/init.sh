#!/bin/bash
set -e
cp -R /src/openvidu /src/openvidusrc
cd /src/openvidusrc
mvn -DskipTests=true clean install
/usr/bin/java -jar /src/openvidusrc/openvidu-server/target/openvidu-server-2.18.0.jar
