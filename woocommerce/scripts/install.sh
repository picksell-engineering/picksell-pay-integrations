curl -o /bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x /bin/wp && wp --info --allow-root && \
wp core install --path="/var/www/html" --url="https://wp.psd2.club" --title="Picksell WooCommerce" \
	--admin_user=picksell --admin_password=picksell --admin_email=picksell@psd2.club --allow-root && \
wp plugin install woocommerce --version=5.0.0 --activate --allow-root && \
wp theme install storefront --activate --allow-root && \
wp plugin install wordpress-importer --activate --allow-root && \
wp import /var/www/html/wp-content/plugins/woocommerce/*/*.xml --authors=create --allow-root
