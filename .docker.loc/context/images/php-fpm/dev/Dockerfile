FROM php:8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Europe/Moscow

# Установка необходимых пакетов
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        zip \
        git \
        libicu-dev \
        libzip-dev \
        nano \
        mc \
        pv \
        htop \
        curl \
        libpng-dev \
        libxml2-dev \
        supervisor \
        wget \
        unzip \
        librabbitmq-dev \
        libssh-dev \
        libssl-dev \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Установка PECL расширений
RUN pecl install xhprof && \
    echo "extension=xhprof.so" > /usr/local/etc/php/conf.d/xhprof.ini

# Установка Xdebug
RUN pecl install xdebug && \
    echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini

# Установка расширений PHP
RUN docker-php-ext-install intl zip opcache pdo pdo_mysql bcmath sockets gd soap sockets
RUN yes | pecl install apcu

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY ./conf/php-fpm/dev/php.ini /usr/local/etc/php/php.ini
COPY ./conf/php-fpm/dev/remote-xdebug.ini /usr/local/etc/php/conf.d/remote-xdebug.ini
COPY ./conf/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./conf/supervisor.ini /etc/supervisord.d/supervisor.ini

### timezone ###
ARG TZ="Europe/Moscow"
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN echo "\ndate.timezone = $TZ" >> /usr/local/etc/php/php.ini

### permissions ###
RUN mkdir -p /var/www/var/cache /var/www/var/log && chmod -R 777 /var/www/var
