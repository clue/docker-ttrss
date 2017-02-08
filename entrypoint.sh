#!/bin/bash
set -e

# remove trailing / if any.
SELF_URL_PATH=${SELF_URL_PATH/%\//}

# extract the root path from SELF_URL_PATH (i.e http://domain.tld/<root_path>).
ROOT_PATH=${SELF_URL_PATH/#http*\:\/\/*\//}
if [ "${ROOT_PATH}" == "${SELF_URL_PATH}" ]; then
    # no root path in SELF_URL_PATH.
    mkdir -p /var/tmp
    ln -sf "/var/www" "/var/tmp/www"
else
    mkdir -p /var/tmp/www
    ln -sf "/var/www" "/var/tmp/www/${ROOT_PATH}"
fi

php /configure-db.php
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
