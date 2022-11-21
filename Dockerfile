FROM php:cli
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" &&\
    php composer-setup.php &&\
    rm composer-setup.php &&\
    mv composer.phar /usr/bin/composer &&\
    apt update &&\
    apt install -y libzip-dev clamav clamdscan clamav-daemon &&\
    docker-php-ext-configure zip &&\
    docker-php-ext-install zip &&\
    sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf
RUN freshclam --foreground
COPY . /opt/filechecker
RUN cd /opt/filechecker &&\
    composer update
ENTRYPOINT ["/opt/filechecker/dockerinit.sh"]
CMD ["0"]

