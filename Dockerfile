FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions — the important one is pdo_mysql
RUN docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        curl \
        zip \
        gd \
        xml \
        dom \
        fileinfo \
        opcache

# Enable built-in extensions that ship with PHP
RUN docker-php-ext-enable opcache

# Work from /app
WORKDIR /app

# Copy project files
COPY . /app

# Railway gives us a port at runtime; default to 8080 for local testing
ENV PORT=8080
EXPOSE 8080

# Start the PHP built-in server, serving from /app
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /app"]