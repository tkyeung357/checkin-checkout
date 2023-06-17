#!/bin/bash
docker build -t push-ops-test .
docker run push-ops-test
