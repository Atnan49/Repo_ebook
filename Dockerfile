# ============================================================
# RepoBook - Dockerfile
# PHP 8.2 + Apache + MySQL extensions + GD (WebP)
# ============================================================
FROM php:8.2-apache

# -----------------------------------------------------------
# 1. Install dependensi sistem & ekstensi PHP
#    - pdo_mysql & mysqli  : koneksi database
#    - gd (webp/jpeg/png)  : konversi cover ke WebP
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_mysql mysqli gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# 2. Aktifkan modul Apache
# -----------------------------------------------------------
RUN a2enmod rewrite headers

# -----------------------------------------------------------
# 3. Konfigurasi Apache  — DocumentRoot → /var/www/html/public
#    + Alias untuk assets & admin agar tetap bisa diakses
# -----------------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Tulis konfigurasi alias & directory dalam satu layer
RUN { \
    echo ''; \
    echo '# === RepoBook Custom Config ==='; \
    echo 'Alias /assets /var/www/html/assets'; \
    echo 'Alias /admin  /var/www/html/admin'; \
    echo ''; \
    echo '<Directory "/var/www/html/assets">'; \
    echo '    Options FollowSymLinks'; \
    echo '    AllowOverride None'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo ''; \
    echo '<Directory "/var/www/html/admin">'; \
    echo '    Options FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo ''; \
    echo '<Directory "/var/www/html/public">'; \
    echo '    Options FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} >> /etc/apache2/apache2.conf

# -----------------------------------------------------------
# 4. Set working directory & salin source code
# -----------------------------------------------------------
WORKDIR /var/www/html
COPY . .

# -----------------------------------------------------------
# 5. Pastikan direktori upload ada & set permissions
# -----------------------------------------------------------
RUN mkdir -p storage/pdfs assets/covers \
    && chown -R www-data:www-data storage assets/covers

# -----------------------------------------------------------
# 6. Expose port & default command (bawaan dari base image)
# -----------------------------------------------------------
EXPOSE 80
