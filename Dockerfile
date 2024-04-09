FROM php:8.2-cli
RUN curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions &&\
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    sed -i -e 's/^memory_limit.*/memory_limit = -1/g' $PHP_INI_DIR/php.ini &&\
    apt update &&\
    apt install -y clamav clamdscan clamav-daemon gdal-bin screen &&\
    sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf &&\
    install-php-extensions @composer ctype dom fileinfo gd iconv intl libxml mbstring simplexml xml xmlwriter zip zlib bz2 phar yaml &&\
    ln -s /usr/local/bin/php /usr/bin/php
COPY dockerinit.sh /opt/dockerinit.sh"
RUN freshclam --foreground
RUN cd /opt &&\
    composer require -o --update-no-dev acdh-oeaw/repo-file-checker &&\
    composer require -o --update-no-dev acdh-oeaw/arche-metadata-crawler
ENTRYPOINT ["/opt/dockerinit.sh"]

