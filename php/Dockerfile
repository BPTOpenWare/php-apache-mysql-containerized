ARG PHP_VERSION="7.4"
ARG ALPINE_VERSION="3.10"
FROM php:${PHP_VERSION:+${PHP_VERSION}-}fpm-alpine${ALPINE_VERSION}

RUN apk update; \
    apk upgrade;
RUN apk add libcurl curl-dev 
RUN apk add bzip2-dev
RUN apk add php7-mbstring

RUN docker-php-ext-install mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install bz2

RUN mkdir /var/www/html/mobile
RUN mkdir /var/www/html/app
RUN mkdir /var/www/vendor
RUN mkdir /var/www/dump
RUN mkdir /var/www/cache
RUN chmod 777 /var/www/cache
COPY php/class.php /var/www/html/class.php
COPY php/enc.php /var/www/html/enc.php
COPY php/GetFile.php /var/www/html/GetFile.php
COPY php/GetImage.php /var/www/html/GetImage.php
COPY php/index.php /var/www/html/index.php
COPY php/login.php /var/www/html/login.php
COPY php/NoSpam.php /var/www/html/NoSpam.php
COPY php/mobileindex.php /var/www/html/mobile/index.php
COPY php/mobilelogin.php /var/www/html/mobile/login.php
COPY php/sessionDBManager.php /var/www/html/sessionDBManager.php
COPY php/genericUserObjects.php /var/www/html/genericUserObjects.php
RUN ln -s /var/www/html/NoSpam.php /var/www/html/mobile/NoSpam.php
RUN ln -s /var/www/html/GetFile.php /var/www/html/mobile/GetFile.php
RUN ln -s /var/www/html/GetImage.php /var/www/html/mobile/GetImage.php

