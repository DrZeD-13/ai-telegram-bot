FROM library/mariadb:10.5.3

### timezone ###
ARG TZ="Europe/Moscow"
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
