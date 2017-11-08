FROM ubuntu:16.04
RUN apt-get update && apt-get install -y locales software-properties-common \
 && rm -rf /var/lib/apt/lists/* \
 && localedef -i en_US -c -f UTF-8 -A /usr/share/locale/locale.alias en_US.UTF-8
ENV LANG en_US.utf8
RUN apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 0C49F3730359A14518585931BC711F9BA15703C6 \
 && echo "deb [ arch=amd64,arm64 ] http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.4 multiverse" >> /etc/apt/sources.list.d/mongodb-org-3.4.list \
 && apt-get update \
 && apt-get install -y mongodb-org \
 && apt-get install -y php7.0-cli php7.0-dev php7.0-curl php7.0-xml php7.0-bcmath php7.0-mbstring pkg-config \
 && echo "mbstring.func_overload 7" >> /etc/php/7.0/cli/php.ini \
 && echo "mbstring.language Neutral" >> /etc/php/7.0/cli/php.ini \
 && pecl config-set php_ini /etc/php/7.0/cli/php.ini \
 && pecl install mongodb \
 && echo "extension=mongodb.so" >> /etc/php/7.0/mods-available/mongodb.ini \
 && ln -s -T /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/cli/conf.d/99-mongodb.ini \
 && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer
RUN mkdir -p /data/db
ENTRYPOINT ["/usr/bin/php"]
