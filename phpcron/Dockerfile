ARG PHP_VERSION="7.4"
ARG ALPINE_VERSION="3.10"
FROM php:${PHP_VERSION:+${PHP_VERSION}-}cli-alpine${ALPINE_VERSION}

RUN apk update; \
    apk upgrade;
RUN apk add libcurl curl-dev 
RUN apk add bzip2-dev
RUN apk add php7-mbstring

RUN docker-php-ext-install mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install bz2
COPY crontab /etc/crontab
COPY odocron.sh /odocron.sh
RUN chmod 755 /odocron.sh
RUN /usr/bin/crontab /etc/crontab

CMD ["/odocron.sh"]
