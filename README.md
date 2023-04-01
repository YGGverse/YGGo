### YGGo! - Open Source Web Search Engine

Written by inspiration to research [Yggdrasil](https://yggdrasil-network.github.io) ecosystem, because of single [Yacy](https://yacy.net/) node was down.
Could be using for crawling regular websites, small business resources, local networks.

The goal - simple interface, clear architecture and lightweight server requirements but effective content discovery.

#### Online examples

[An official node, that indexing only the local network](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo)  
[http://94.140.114.241/yggo](http://94.140.114.241/yggo) (web mirror)

#### Requirements

```
php 8
php-php
php-pdo
curl-curl
sqlite / fts5
```

#### Installation 

* The webroot dir is under `/public` 
* Single configuration file placed here `/config/app.php` where important option is maybe DB settings just
* By default, script automaticaly generate the database file in `/storage` folder (where have supposed to collect other variative and tmp data - like logs, or unique gravatars for sites without favicons)
* Set up the `/crontab/crawler.php` script for execution every the minute, but it mostly related of the configs and target network volume
* Script has no MVC model, because of super simple. It's is just 2 files, and everything else stored incapsulated in `library` classes.

#### TODO / ideas

* [x] Web pages full text ranking search 
* [ ] Make search results pagination
* [ ] Improve yggdrasil links detection, add .ygg domain sone support
* [ ] Images search (basically implemented but requires testing and some performance optimization)
* [ ] Distributed index data sharing between the nodes trough service API
