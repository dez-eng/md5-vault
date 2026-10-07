FROM php:8.2-apache

# 启用官方生产配置：display_errors=Off（避免 warning 泄露路径）、request_order=GP。
# php:8.2-apache 默认不带 php.ini，缺省值是开发取向的；另外 production 模板里 expose_php 那行
# 是注释掉的（内置默认 On），所以另用 conf.d 文件关掉，避免响应头暴露 PHP 版本。
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf '%s\n' 'expose_php=Off' > "$PHP_INI_DIR/conf.d/zz-hardening.ini"

# 站点配置：关闭目录列表（本题只暴露对账接口，不需要索引页）
RUN printf '%s\n' \
    'ServerName localhost' \
    'DirectoryIndex index.php' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride None' \
    '    Require all granted' \
    '</Directory>' \
    '<Directory /var/www/html/inc>' \
    '    Require all denied' \
    '</Directory>' \
    > /etc/apache2/conf-available/keyvault.conf \
 && a2enconf keyvault

WORKDIR /var/www/html
COPY src/ /var/www/html/

# php:8.2-apache 自带 /var/www/html/index.html，Debian 默认 DirectoryIndex 会优先返回它，
# 使站点首页变成 "It works!" 而不是题目首页；这里删掉，并在上面的 conf 里显式指定 index.php
RUN rm -f /var/www/html/index.html

RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \;

# 启动脚本：注入动态 Flag -> 清理环境变量 -> 启动 Apache
COPY start.sh /start.sh
RUN sed -i 's/\r$//' /start.sh && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
