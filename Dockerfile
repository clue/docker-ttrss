FROM ubuntu
MAINTAINER Christian Lück <christian@lueck.tv>

RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y \
  nginx git supervisor php5-fpm php5-cli php5-curl php5-gd php5-json \
  php5-pgsql php5-mysql && apt-get clean

# add ttrss as the only nginx site
ADD ttrss.nginx.conf /etc/nginx/sites-available/ttrss
RUN ln -s /etc/nginx/sites-available/ttrss /etc/nginx/sites-enabled/ttrss
RUN rm /etc/nginx/sites-enabled/default

ENV CODE_DIR /var/www

# install ttrss and patch configuration
RUN git clone https://github.com/gothfox/Tiny-Tiny-RSS.git $CODE_DIR
WORKDIR $CODE_DIR
RUN cp config.php-dist config.php
RUN sed -i -e "/'SELF_URL_PATH'/s/ '.*'/ 'http:\/\/localhost\/'/" config.php
RUN chown www-data:www-data -R $CODE_DIR

# install feedly theme
WORKDIR $CODE_DIR/themes
RUN git clone https://github.com/levito/tt-rss-feedly-theme.git
RUN ln -s tt-rss-feedly-theme/feedly.css
RUN ln -s tt-rss-feedly-theme/feedly

# install videoframes plugin
WORKDIR $CODE_DIR/plugins
RUN git clone https://github.com/tribut/ttrss-videoframes.git
RUN ln -s ttrss-videoframes/videoframes

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

