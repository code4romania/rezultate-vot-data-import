#!/command/with-contenv sh

cd /var/www

php artisan migrate --force

php artisan config:cache
php artisan event:cache
php artisan route:cache

php artisan about