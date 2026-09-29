# Production image: stock WordPress with this plugin pre-installed.
FROM wordpress:7.1-php8.4-apache

LABEL org.opencontainers.image.title="Admin Conflict Logger (WordPress)" \
      org.opencontainers.image.description="WordPress with the Admin Conflict Logger plugin pre-installed" \
      org.opencontainers.image.licenses="GPL-2.0-or-later"

# Install the plugin into the stock WordPress source tree so it is present
# on first boot (fresh volume) as well as on rebuilt containers.
COPY . /usr/src/wordpress/wp-content/plugins/admin-conflict-logger/

# Drop dev-only files from the shipped plugin copy.
RUN rm -rf /usr/src/wordpress/wp-content/plugins/admin-conflict-logger/vendor \
           /usr/src/wordpress/wp-content/plugins/admin-conflict-logger/node_modules \
           /usr/src/wordpress/wp-content/plugins/admin-conflict-logger/tests \
    && chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/admin-conflict-logger

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://localhost/wp-admin/install.php || exit 1
