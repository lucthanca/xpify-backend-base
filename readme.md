
## Getting Started
Hướng dẫn setup project

### Prerequisites

Setup môi trường đáp ứng các yêu cầu sau:
- PHP 8.1 (các extensions: bcmath,ctype,curl,dom,common,gd,iconv,intl,mbstring,simplexml,soap,xsl,zip,sockets,mysql,bz2)
- Mariadb-10.4
- Varnish 6
- Composer 2.2.x
- nginx
- php8.1-fpm
- ```ini
  # php.ini
  memory_limit = 768M
  max_execution_time=18000
  max_input_nesting_level = 64
  max_input_time = 60

### Installation

1. Clone project, cd vào thư mục project
2. Copy file auth.json.sample -> auth.json sau đó vào marketplace.magento.com để lấy thông tin access key rồi nhét vào file auth.json vừa tạo.
3. Chạy lệnh `composer install`
4. Sau khi lệnh đã hoàn thành, tiếp tục chạy lệnh setup install
    ```sh
    # Phân quyền thư mục
    find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
    find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
    
    # Cài đặt site
    php8.1 bin/magento setup:install \
    --base-url="https://backend.com/" \
    --db-host="localhost" \
    --db-name="db_name" \
    --db-user="db_user" \
    --db-password="db_pw" \
    --admin-firstname="Nguyen Van" \
    --admin-lastname="A" \
    --admin-email="anv@example.com" \
    --admin-user="admin" \
    --admin-password="admin123" \
    --use-rewrites="1" \
    --backend-frontname="admin" \
    --use-secure=1 \
    --use-secure-admin=1
   
   # Tắt các module không cần thiết
   php8.1 bin/magento mod:dis \
   Magento_AdobeIms \
   Magento_AdobeImsApi \
   Magento_EavGraphQl \
   Magento_CmsGraphQl \
   Magento_CatalogGraphQl \
   Magento_AdminAdobeIms \
   Magento_UrlRewriteGraphQl \
   Magento_AdvancedSearch \
   Magento_Search \
   Magento_CatalogSearch
    ```
5. Vào app/etc/env.php sửa lại phần db -> initStatements => "SET NAMES utf8mb4;"
