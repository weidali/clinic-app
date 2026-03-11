FROM php:8.2-fpm

# Аргументы
ARG user=vitaliy
ARG uid=1000

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание пользователя для запуска приложения
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Копирование конфигурации PHP
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Настройка прав
WORKDIR /var/www
COPY . /var/www
RUN chown -R $user:$user /var/www

USER $user

EXPOSE 9000
CMD ["php-fpm"]
