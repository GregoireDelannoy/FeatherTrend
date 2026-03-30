FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libgomp1 \
    libpng-dev \
    libjpeg-dev \
    libffi-dev && apt clean

# Install PHP extensions required for Doctrine/Symfony and Postgres
RUN docker-php-ext-install pdo_pgsql zip

# Install GD extension (required by Imagine\Gd used for image pre-processing)
RUN docker-php-ext-configure gd --with-jpeg && docker-php-ext-install gd

# Get composer binary
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install and enable PHP FFI (required by ankane/onnxruntime which uses FFI to load libonnxruntime.so)
RUN docker-php-ext-install ffi
RUN echo -e "extension=ffi\nffi.enable = true" > /usr/local/etc/php/conf.d/ffi.ini

WORKDIR /app
