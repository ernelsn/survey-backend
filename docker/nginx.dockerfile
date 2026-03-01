FROM nginx:stable-alpine

ARG UID
ARG GID
ENV UID=${UID}
ENV GID=${GID}

RUN addgroup -g ${GID} laravel \
    && adduser -D -u ${UID} -G laravel -s /bin/bash laravel

# Update Nginx config to use the 'laravel' user
RUN sed -i "s/user  nginx/user laravel/g" /etc/nginx/nginx.conf

# Copy Nginx configuration
COPY ./nginx/default.conf /etc/nginx/conf.d/

# Ensure the document root is properly set
RUN mkdir -p /var/www/html

EXPOSE 80

WORKDIR /var/www/html