ARG APACHE_VERSION="2.4.41"
FROM httpd:${APACHE_VERSION:+${APACHE_VERSION}-}alpine

RUN apk update; \
    apk upgrade;
    
RUN apk add apache2-ssl;

RUN mkdir /usr/local/apache2/htdocs/mobile
RUN mkdir /usr/local/apache2/htdocs/js
RUN mkdir /usr/local/apache2/htdocs/css
RUN mkdir /usr/local/apache2/htdocs/images
RUN mkdir /usr/local/apache2/htdocs/app
RUN mkdir /usr/local/apache2/htdocs/app/css
RUN mkdir /usr/local/apache2/htdocs/app/img
RUN mkdir /usr/local/apache2/htdocs/app/html
RUN mkdir /usr/local/apache2/htdocs/app/JavaScript
RUN mkdir /etc/apachessl
RUN rm /usr/local/apache2/htdocs/index.html
COPY JavaScript/* /usr/local/apache2/htdocs/js/
COPY css/* /usr/local/apache2/htdocs/css/
COPY images/* /usr/local/apache2/htdocs/images/
RUN ln -s /usr/local/apache2/htdocs/images /usr/local/apache2/htdocs/css/images

# Copy apache vhost file to proxy php requests to php-fpm container
COPY odoapache.conf /usr/local/apache2/conf/odoapache.conf
COPY options-ssl-apache.conf /usr/local/apache2/conf/options-ssl-apache.conf
RUN echo "Include /usr/local/apache2/conf/odoapache.conf" \
    >> /usr/local/apache2/conf/httpd.conf

EXPOSE 80 443

