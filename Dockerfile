FROM alpine:latest

RUN set -x ; \
  addgroup -g 82 -S www-data ; \
  adduser -u 82 -D -S -G www-data www-data && exit 0 ; exit 1
  
RUN apk --update add \
  php7-openssl \
  php7 \
  php7-json \
  php7-phar \
  php7-mbstring \
  curl 

COPY composer.json composer.json
COPY composer.lock composer.lock

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader && rm -rf /root/.composer
RUN composer dump-autoload --optimize

COPY . ./
RUN chown -R www-data:www-data /app/www

WORKDIR /app/www

CMD ["php", "start_io.php", "start"]