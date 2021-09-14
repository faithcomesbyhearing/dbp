# Setting up DBP locally

## Requirements 
* PHP 7.4.+
* Composer
* Mysql@5.7
* Memcached
* Valet (or your preferred service manager/server)

## Installing the basic requirements (Mac)
* `brew update`
* `brew upgrade`
* Install PHP 7.4.+ `brew install mcrypt php@7.4`
* Start php service: `brew services start php@7.4`
* `brew link php@7.4 --force`
* `php -v` --> should give you 7.4 now on terminal if not, close session of term and open a new one (Restart)
* Install Mysql `brew install mysql@5.7`
* Install memcached:
* - `brew install libmemcached`
* - `pecl install memcached`
* - Pecl will ask you the path of the library, type `yes`
* - Check if the php module has been added: `php -m | grep memcached`
* - Start the service: `/usr/local/opt/memcached/bin/memcached &`
* Start mysql service: `brew services start mysql@5.7`
* Finally, install composer: `brew install composer`
* Install valet: `composer global require laravel/valet`
* If you have a problem with the “Changed current directory to”. 
You need to add this folder to your unix path. You can do this by running the following: `export PATH=$PATH:~/.composer/vendor/bin`
* valet install

## Serving dbp
Once Valet is installed, you’re ready to start serving sites. Valet provides two commands to help you serve your Laravel sites: `park` and `link`.
* Set your env file APP_URL and API_URL to the url you'll want to go i.e: APP_URL=https://dbp.test and API_URL=https://dbp.test/api
* cd into your dbp folder
* run  `valet park`

## Setting the db environment:
* Install sequel pro to visualize the databases: https://sequelpro.com/download
* To decrypt the databases you'll need: `openssl enc -aes-256-cbc -d -in encypted_db_name -out decrypted_db_name -k "your decript password"`

## Set the databases:
### Adding a database:
On Sequel Pro:
* Go to "database"->"Add database"
* For the users db, name it dbp_users
* For the dbp prod, name it dbp_prod
* For each database, go to "File"->"Import" and select the sql file you have

### running migrations:
Go to your terminal
* cd into your dbp root folder
* run `php artisan migrate` you can use an additional ":status" to the command to see which migrations have run thus far
