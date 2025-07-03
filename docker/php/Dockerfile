# docker/php/Dockerfile

# Start from the official PHP 8.2 FPM image
FROM php:8.4-fpm

# Set environment to non-interactive to prevent prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Block to create a user that matches your host user
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} -o tomek
RUN useradd -l -u ${UID} -g tomek -m tomek

# Install system dependencies & PHP Extensions
RUN apt-get update && apt-get install -y \
    # System utilities
    curl \
    unzip \
    # Libraries for existing extensions
    libzip-dev \
    libicu-dev \
    # ✅ ADDED: Libraries for the GD extension from your CFK project
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    && \
    # ✅ ADDED: Configure the GD extension to support common image formats
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    # Install all required PHP extensions, including GD
    docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    zip \
    intl \
    gd \
    && \
    # Clean up apt cache to reduce final image size
    rm -rf /var/lib/apt/lists/*

# Copy the Composer executable from the official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy the entrypoint script
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]