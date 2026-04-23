FROM node:20

WORKDIR /var/www/app

COPY docker/app/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
