FROM php:7.2-cli

RUN apt-get update \
 && apt-get install -y mongodb git

RUN pecl install mongodb \
 && docker-php-ext-install bcmath pdo_mysql mbstring opcache pcntl \
 && docker-php-ext-enable mongodb

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer && composer global require hirak/prestissimo --no-plugins --no-scripts

WORKDIR /app

COPY . /app

RUN composer install

ENTRYPOINT /etc/init.d/mongodb start && vendor/bin/phpunit
