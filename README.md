# Roundcube Next PHP Server

This is the Roundcube Next JMAP server component written in PHP.

## Prerequisites

Primarily requires PHP 5.5 or newer with the standard components such as 
Session support activated.

The only modus operandi currently implemented is in conjunction with the 
[Perl JMAP proxy](https://github.com/jmapio/jmap-perl). Therefore you need 
to install and start the JMAP proxy somewhere in your environment. The 
most concenient way to do so is by running a Docker image from either 

https://hub.docker.com/r/rpignolet/jmap-perl/ or
https://kanarip.wordpress.com/2015/10/08/jmap-proxy-docker-image/

## Installation (with composer)

If composer is not yet on your system, [install it](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).

From the project directory, the next step is to execute
```
$ php composer.phar install
```

Now over to configuration. First, copy the `config/config.yaml.dist` into 
`config/config.yaml` to create your local config file.

Then edit `config.yaml` and adjust the `jmapproxy` options according your 
environment.

## Running

Once the dependencies are installed and the config file adjusted, 
you can configure your existing webserver to serve the the `public_html` 
directory as document root for the host/location you want the Roundcube 
server to be available at.

As an alternative, simply run the PHP integrated webserver with the 
following command from the project directory:

```
$ php -S 0.0.0.0:80 -t public_html public_html/index.php
````

## License

This program is free software: you can redistribute it and/or modify it 
under the terms of the GNU General Public License as published by the 
Free Software Foundation, either version 3 of the License, or (at your 
option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see [www.gnu.org/licenses/](http://www.gnu.org/licenses/gpl-3.0).
