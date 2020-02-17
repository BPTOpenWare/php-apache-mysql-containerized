ARG PHP_VERSION="7.4"
ARG ALPINE_VERSION="3.10"
FROM php:${PHP_VERSION:+${PHP_VERSION}-}cli-alpine${ALPINE_VERSION}

RUN apk update; \
    apk upgrade;
RUN apk add --no-cache bash
RUN apk add libcurl curl-dev 
RUN apk add bzip2-dev
RUN apk add php7-mbstring

RUN docker-php-ext-install mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install bz2
RUN mkdir /phpcron
RUN mkdir /phpcron/jobs
RUN mkdir /phpcron/vendor
COPY odocrontab /etc/crontabs/phpcron
COPY odocron.sh /phpcron/odocron.sh
COPY enc.php /phpcron/enc.php
RUN addgroup -S phpcron && adduser -S phpcron -G phpcron
RUN chmod 755 /phpcron/odocron.sh

CMD ["/phpcron/odocron.sh"]
