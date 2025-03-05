#!/bin/bash

# crate swap
sudo /bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024
sudo /sbin/mkswap /var/swap.1
sudo /sbin/swapon /var/swap.1

sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get upgrade -y

#echo "
#   Installing MySql - dbName: snf | user:root | password:rootpass
#***************************************************************"
#sudo debconf-set-selections <<< "mysql-server mysql-server/root_password password rootpass"
#sudo debconf-set-selections <<< "mysql-server mysql-server/root_password_again password rootpass"
#
#sudo apt-get install -y mysql-common mysql-server mysql-client
#mysql -u root -prootpass  -e "CREATE DATABASE snf;"

echo "
   Installing PHP ...
***************************************************************"
sudo apt-get install -y zip unzip imagemagick
sudo apt-get install -y nginx
sudo apt-get install -y curl git redis-server
sudo apt-get install -y software-properties-common python-software-properties
sudo apt-get install -y php8.3-common php8.3-cli php8.3-fpm
sudo apt-get install -y php8.3-{bz2,curl,mysql,readline,xml,gd,dev,mbstring,opcache,zip,xsl,dom,intl,redis,xdebug,imagick,mcrypt}

cd /vagrant/

sudo bash -c "echo '
127.0.0.1       localhost

# The following lines are desirable for IPv6 capab
::1     ip6-localhost   ip6-loopback
fe00::0 ip6-localnet
ff00::0 ip6-mcastprefix
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
ff02::3 ip6-allhosts
127.0.1.1       ubuntu-xenial   ubuntu-xenial
' > /etc/hosts"

sudo bash -c "echo 'server {
    listen 80;
    sendfile off;
    root /vagrant/public;
    index index.php;
    server_name solidarity.local;
    location / {
         try_files \$uri \$uri/ /index.php?\$args;
    }
    client_max_body_size 16M;
    client_body_buffer_size 2M;
    proxy_connect_timeout       300;
    proxy_send_timeout          300;
    proxy_read_timeout          300;
    fastcgi_connect_timeout 300;
    fastcgi_send_timeout 300;
    fastcgi_read_timeout 300;

    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;

    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param APPLICATION_ENV development;
    }
}' > /etc/nginx/sites-available/solidarity.local"

if [ ! -L "/etc/nginx/sites-enabled/solidarity.local" ]; then
	sudo ln -s /etc/nginx/sites-available/solidarity.local /etc/nginx/sites-enabled/solidarity.local
fi
sudo service nginx restart
echo 'installing composer ...'
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'e5325b19b381bfd88ce90a5ddb7823406b2a38cff6bb704b0acc289a09c8128d4a8ce2bbafcd1fcbdc38666422fe2806') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
mv composer.phar /bin/composer
echo 'composer downloaded.'
echo 'composer install.'
composer install
echo 'composer install done.'
