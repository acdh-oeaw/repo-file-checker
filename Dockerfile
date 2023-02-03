FROM php:8.1-cli
RUN curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions &&\
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    apt update &&\
    apt install -y clamav clamdscan clamav-daemon &&\
    sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf &&\
    install-php-extensions @composer fileinfo iconv intl mbstring simplexml zip zlib bz2 phar
RUN freshclam --foreground
COPY . /opt/filechecker
RUN cd /opt/filechecker &&\
    composer update -o --no-dev
ENTRYPOINT ["/opt/filechecker/dockerinit.sh"]
CMD ["0"]

