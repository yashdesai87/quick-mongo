FROM php:8.3-apache

LABEL org.opencontainers.image.title="Quick Mongo" \
      org.opencontainers.image.description="Read-only MongoDB browser, drop it next to your Mongo service" \
      org.opencontainers.image.source="https://github.com/yashdesai87/quick-mongo" \
      org.opencontainers.image.licenses="Apache-2.0"

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions mongodb \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers expires \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/quick-mongo.conf \
    && sed -i 's/^expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini" \
    && a2enconf quick-mongo


COPY . /var/www/html/
