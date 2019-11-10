# Installing

#### Dependencies

- PHP >= 7.1
- PECL
- OpenSSL PHP Extension
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Composer

### Running on OSX (Local)
Setting up the API takes approximately 30 minutes. If you don't already have [git]('https://git-scm.com/book/en/v2/Getting-Started-Installing-Git') or [homebrew]('https://brew.sh/') installed. You will want to install those now.

##### Install and/or update Homebrew to the latest version using brew update
`/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"`

`brew update`

##### Install PHP 7.2 using Homebrew via brew install homebrew/core/php
`brew install homebrew/core/php && brew install composer && brew install mysql`

##### Install Valet with Composer via composer global require laravel/valet
`composer global require laravel/valet`

##### Add `~/.composer/vendor/bin` to paths and restart terminal
`sudo nano /etc/paths`

##### Set up valet
If this is your first time working with Valet, set it up following these instructions: https://laravel.com/docs/valet

Then ensure you've set your sites directory up something like this:

`valet install && mkdir ~/Sites && cd ~/Sites && valet park`

##### Install Repo
` git clone https://github.com/digitalbiblesociety/dbp.git `

cd down into the directory

`cd dbp`

and Run composer install

`composer install`

##### Set up a valid local .env file
`cp .env.example .env`

##### Generate a new application key:

`php artisan key:generate`

##### Link the API subdomain
`valet link api.dbp`

##### Secure the Valet domains
`valet secure`

##### Install Node and run npm install
`brew install node && npm install`

##### Import a copy of the live database using your preferred method: phpMyAdmin, Sequel pro, etc.

### Running on Windows
##### (Work in Progress)

One useful set of instruction can be found on https://github.com/cretueusebiu/valet-windows/blob/master/README.md#documentation

>Before installation, make sure that no other programs such as Apache or Nginx are binding to your local machine's port 80. <br> Also make sure to open your preferred terminal (CMD, Git Bash, PowerShell, etc.) as Administrator. 
>
>- If you don't have PHP installed, open PowerShell (3.0+) as Administrator and run: 
>
> # PHP 7.3
> ```bash
> Set-ExecutionPolicy RemoteSigned; [System.Net.ServicePointManager]> ::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12; > Invoke-WebRequest -Uri "https://github.com/cretueusebiu/> valet-windows/raw/master/bin/php73.ps1" -OutFile > $env:temp\php73.ps1; .$env:temp\php73.ps1
> 
> # PHP 7.2
> Set-ExecutionPolicy RemoteSigned; [System.Net.ServicePointManager]> ::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12; > Invoke-WebRequest -Uri "https://github.com/cretueusebiu/> valet-windows/raw/master/bin/php72.ps1" -OutFile > $env:temp\php72.ps1; .$env:temp\php72.ps1
> ```
> 
> > This script will download and install PHP for you and add it to > your environment path variable. PowerShell is only required for > this step.
> 
> - If you don't have Composer installed, make sure to [install]> (https://getcomposer.org/Composer-Setup.exe) it.
> 
> - Install Valet with Composer via `composer global require > cretueusebiu/valet-windows`.
> 
> - Run the `valet install` command. This will configure and install > Valet and register Valet's daemon to launch when your system > starts.
> 
> - If you're installing on Windows 10, you may need to [manually > configure](http://mayakron.altervista.org/wikibase/show.php?> id=AcrylicWindows10Configuration) Windows to use the Acrylic > DNS proxy.
> 
> Valet will automatically start its daemon each time your machine > boots. There is no need to run `valet start` or `valet install` > ever again once the initial Valet installation is complete.
> 
> For more please refer to the official documentation on the [Laravel > website](https://laravel.com/docs/5.8/valet#serving-sites).
> 
> ## Known Issues
> 
> - When sharing sites the url will not be copied to the clipboard.
> - You must run the `valet` commands from the drive where Valet is > installed, except for park and link. See [#12](https://> github.com/cretueusebiu/valet-windows/issues/> 12#issuecomment-283111834).
> - If your machine is not connected to the internet you'll have to > manually add the domains in your `hosts` file or you can > install the "Microsoft Loopback Adapter" as this simulates an > active local network interface that Valet can bind too.
> 

***The Following Error is where the Windows 10 Install gets stuck:***
> Installation failed, deleting ./composer.json.

If you don't already have [git]('https://git-scm.com/downloads') and [Chocolatey]('https://package.chocolatey.org/install#individual') installed, you will want to install those now.

##### Installing Packages
Run **PowerShell** "Run as Administrator" mode with elevated privileges
```PowerShell
choco install php -y
```
You should see output like: `The install of php was successful. Software installed to 'C:\tools\php73'`

```PowerShell
choco install composer -y
```

This should now add `C:\tools\php73` and `C:\ProgramData\ComposerSetup\bin` to your windows PATH variables accessible from the command line. Close and reopen PowerShell and try the following two commands to confirm this.

```PowerShell
composer -V
php -?
```


- https://windows.php.net/download#php-7.2

#### Installing on Ubuntu 18

##### Install Packages
```bash
apt-get install software-properties-common
add-apt-repository -y 'ppa:ondrej/php'
add-apt-repository -y 'ppa:ondrej/nginx'
apt-get update
sudo apt-get -y install gcc npm curl gzip git tar software-properties-common nginx-full composer
sudo apt-get -y install php7.2-fpm php7.2-xml php7.2-bz2 php7.2-zip php7.2-mysql php7.2-intl php7.2-gd php7.2-curl php7.2-soap php7.2-mbstring php7.2-memcached
```
##### Configure PHP
```bash
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/g' /etc/php/7.2/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/g' /etc/php/7.2/fpm/php.ini
sed -i 's/max_input_time = 60/max_input_time = 300/g' /etc/php/7.2/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 28M/g' /etc/php/7.2/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 512M/g' /etc/php/7.2/fpm/php.ini
service php7.2-fpm restart
```
##### Create a Site
```bash
git clone https://github.com/AfzalH/lara-server.git && cd lara-server
echo 'Enter Site Domain [dbp4.org]:' && read site_com
cp nginx/srizon.com /etc/nginx/sites-available/$site_com
sed -i "s/srizon.com/${site_com}/g" /etc/nginx/sites-available/$site_com
mkdir /var/www/$site_com
mkdir /var/www/$site_com/public
touch /var/www/$site_com/public/index.php
ln -s /etc/nginx/sites-available/$site_com /etc/nginx/sites-enabled/$site_com
service nginx reload
```
##### Edit to test
```bash
nano /var/www/$site_com/public/index.php
```
##### Remove test
```bash
rm -rf /var/www/$site_com/public
```
##### Clone Repo
```bash
git clone https://github.com/digitalbiblesociety/dbp.git /var/www/$site_com
cd /var/www/$site_com
sudo composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
sudo npm install
```

#### Permissions
```bash
sudo find $site_com/ -type d -exec chmod 755 {} ;
sudo find $site_com/ -type d -exec chmod ug+s {} ;
sudo find $site_com/ -type f -exec chmod 644 {} ;
sudo chown -R www-data:www-data $site_com
sudo chmod -R 755 $site_com/storage
sudo chmod -R 755 $site_com/bootstrap/cache/
```
