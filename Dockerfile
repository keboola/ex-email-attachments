FROM php:7.2
ENV DEBIAN_FRONTEND noninteractive

WORKDIR /root
RUN cd && curl -sS https://getcomposer.org/installer | php && ln -s /root/composer.phar /usr/local/bin/composer

COPY . /code
WORKDIR /code

RUN composer install --prefer-dist --no-interaction

CMD php ./src/app.php run /data