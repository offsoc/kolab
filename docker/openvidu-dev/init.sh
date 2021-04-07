#!/bin/bash
cd /src/openvidu/
mvn -DskipTests=true clean install
/usr/bin/java -jar /src/openvidu/openvidu-server/target/openvidu-server-2.16.0.jar
