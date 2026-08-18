FROM nginx:latest

COPY ./conf/nginx/conf.d /etc/nginx/conf.d

ARG PHP_FPM_SERVICE_NAME
RUN sed -i s/%php-fpm-service-name%/${PHP_FPM_SERVICE_NAME}/g /etc/nginx/conf.d/default.conf