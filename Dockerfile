FROM alpine:3.24 AS app
LABEL Maintainer="BohwaZ <https://bohwaz.net/>" \
    Description="oPodSync"

RUN apk --no-cache add php85 php85-ctype php85-session php85-sqlite3 \
    && ln -s /usr/bin/php85 /usr/local/bin/php

# Setup document root
RUN mkdir -p /var/www
RUN mkdir -p /var/www/server
RUN mkdir -p /var/www/server/data

# Add application
WORKDIR /var/www/
COPY server /var/www/server/
COPY config.dist.php /var/www/config.dist.php

EXPOSE 8080

VOLUME ["/var/www/server/data"]

ENV PHP_CLI_SERVER_WORKERS=2
CMD ["php", "-S", "0.0.0.0:8080", "-t", "server", "server/index.php"]

FROM app AS test
RUN apk --no-cache add php85-dom php85-pcntl
COPY test /var/www/test/
CMD ["php", "test/start.php"]

FROM app AS production
