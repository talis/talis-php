FROM php:7.3.33-cli

WORKDIR /var/talis-php

RUN apt-get update && apt-get install -y --no-install-recommends \
  ca-certificates \
  curl \
  git \
  unzip \
  zip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer:2.9.30 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.9.5 /usr/bin/composer /usr/local/bin/

RUN install-php-extensions xdebug
