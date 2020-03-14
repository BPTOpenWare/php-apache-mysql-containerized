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
RUN mkdir /phpcron/vendor
RUN rm -rf /etc/periodic/
RUN mkdir /etc/periodic
COPY enc.php /phpcron/enc.php
COPY odocron.sh /phpcron/odocron.sh
COPY odocrontab /etc/crontabs/root
RUN chmod 755 /phpcron/odocron.sh
RUN chown root /etc/crontabs/root
RUN chgrp root /etc/crontabs/root
RUN chmod ug+rw /etc/crontabs/root

CMD ["/phpcron/odocron.sh"]
