FROM alpine:latest
  
RUN apk --update add \
  php7-openssl \
  php7 \
  php7-json \
  php7-phar \
  php7-mbstring \
  php7-zmq \
  php7-posix \
  php7-pcntl \
  php7-session \
  php7-curl \
  curl 

COPY composer.json composer.json
COPY composer.lock composer.lock

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader && rm -rf /root/.composer
RUN composer dump-autoload --optimize

COPY . ./
RUN chown -R www-data:www-data /app/src

WORKDIR /app/src

CMD ["php", "start_io.php", "start"]