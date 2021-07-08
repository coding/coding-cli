FROM php:8.0-cli

RUN apt-get update \
    && apt-get install -y libzip-dev
RUN docker-php-ext-install zip

COPY builds/coding /usr/local/bin/

ENTRYPOINT ["coding"]
CMD ["list"]
