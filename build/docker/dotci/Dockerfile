FROM debian:jessie
MAINTAINER Thomas Müller "deepdiver@owncloud.com"

ENV HOME /root
ENV DEBIAN_FRONTEND noninteractive

# install basic tools
RUN apt-get -y update
RUN apt-get -y install git curl wget

# install owncloud dependencies
RUN apt-get -y install php5 php5-sqlite php5-pgsql php5-mysqlnd php5-gd php5-intl php5-curl php5-ldap php5-dev php5-xdebug 

# install php tools
RUN wget https://phar.phpunit.de/phpunit.phar && \
    chmod +x phpunit.phar && \
    mv phpunit.phar /usr/local/bin/phpunit
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# install nodejs
RUN curl -sL https://deb.nodesource.com/setup | bash - && \
    apt-get -y install nodejs

# setup locale
RUN apt-get -y install locales && \
    echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen && locale-gen

# setup user and workdir
RUN chmod a+w /var
RUN useradd -ms /bin/bash dotci
USER dotci

CMD ["/sbin/my_init"]

