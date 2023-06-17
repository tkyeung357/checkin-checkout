FROM php:7.4-cli
COPY . /usr/src/push-test
WORKDIR /usr/src/push-test
CMD [ "php", "./main.php" ]
