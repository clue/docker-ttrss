FROM ubuntu
MAINTAINER Christian Lück <christian@lueck.tv>

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y \
        nginx supervisor php5-fpm php5-cli php5-curl php5-gd php5-json \
        php5-pgsql php5-mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# add ttrss as the only nginx site
ADD ttrss.nginx.conf /etc/nginx/sites-available/ttrss
RUN ln -s /etc/nginx/sites-available/ttrss /etc/nginx/sites-enabled/ttrss
RUN rm /etc/nginx/sites-enabled/default

# install ttrss and patch configuration
RUN mkdir /var/www \
    && apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y curl --no-install-recommends && rm -rf /var/lib/apt/lists/* \
    && curl -SL https://github.com/gothfox/Tiny-Tiny-RSS/archive/master.tar.gz | tar xvz -C /var/www --strip-components 1 \
    && apt-get purge -y --auto-remove curl \
    && chown www-data:www-data -R /var/www

WORKDIR /var/www
RUN cp config.php-dist config.php
RUN sed -i -e "/'SELF_URL_PATH'/s/ '.*'/ 'http:\/\/localhost\/'/" config.php

# expose only nginx HTTP port
EXPOSE 80

# expose default database credentials via ENV in order to ease overwriting
ENV DB_NAME ttrss
ENV DB_USER ttrss
ENV DB_PASS ttrss

# always re-configure database with current ENV when RUNning container, then monitor all services
ADD configure-db.php /configure-db.php
ADD supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD php /configure-db.php && supervisord -c /etc/supervisor/conf.d/supervisord.conf

