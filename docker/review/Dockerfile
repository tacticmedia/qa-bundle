# The review page as a container, so a project with no PHP toolchain can read
# its screenshot sets. Capture does not run here; the browsers are on the host.
FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions gd @composer \
    && apk add --no-cache curl

# The base image sets Caddy's data directory to /data, which is where the
# reviewed project is mounted. Set the Caddy directories elsewhere.
ENV XDG_DATA_HOME=/home/app/.local/share \
    XDG_CONFIG_HOME=/home/app/.config

RUN addgroup -g 1000 app \
    && adduser -u 1000 -G app -D -h /home/app app \
    && mkdir -p /data/var/screenshots /data/var/review "$XDG_DATA_HOME" "$XDG_CONFIG_HOME" \
    && chmod -R a+rwX /data /home/app

# The layout is the same as the repository, so the ../.. path repository in
# docker/review/composer.json resolves to the same location as on a developer machine.
WORKDIR /app/docker/review

COPY composer.json /app/composer.json
COPY src/ /app/src/
COPY config/ /app/config/
COPY assets/ /app/assets/
COPY templates/ /app/templates/
COPY resources/ /app/resources/
COPY docker/review/ /app/docker/review/

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:8000 \
    SERVER_ROOT=/app/docker/review/public \
    QA_SCREENSHOTS_DIR=/data/var/screenshots \
    QA_REVIEW_DIR=/data/var/review

RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && chown -R app:app /app

USER app

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost:8000/_dev/screenshots -o /dev/null || exit 1
