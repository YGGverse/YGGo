### YGGo! - Open Source Web Search Engine

Written by inspiration to research [Yggdrasil](https://yggdrasil-network.github.io) ecosystem, because of single [YaCy](https://yacy.net/) node was down.
Could be useful for crawling regular websites, small business resources, local networks.

The project goal - simple interface, clear architecture and lightweight server requirements.

#### Online examples

[http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo)  
[http://94.140.114.241/yggo/](http://94.140.114.241/yggo)

#### Screenshotes

![Search page](https://github.com/d47081/YGGo/blob/main/media/search.png?raw=true)

https://github.com/d47081/YGGo/tree/main/media

#### Requirements

```
php 8
php-php
php-pdo
curl-curl
sqlite / fts5
```

#### Installation 

* The webroot dir is `/public` 
* Single configuration file placed here `/config/app.php.txt` and need to be configured and renamed to `/config/app.php`
* By the idea, script automaticaly generates database structure in `/storage` folder (where could be nice to collect other variative and tmp data - like logs, etc). Make sure storage folder writable.
* Set up the `/crontab/crawler.php` script for execution every the minute, but it mostly related of the configs and targetal network volume, there is no debug implemented yet, so let's silentize it by `/dev/null`
* Script has no MVC model, because of super simple. It's is just 2 files, and everything else stored incapsulated in `/library` classes.

#### TODO / ideas

* [x] Web pages full text ranking search 
* [ ] Make search results pagination
* [ ] Improve yggdrasil links detection, add .ygg domain zone support
* [ ] Make page description visible - based on the cached content dump, when website description tag not available, add condition highlights
* [ ] Images search (basically implemented but requires testing and some performance optimization)
* [ ] Deprecated pages index cleaner (404 http codes etc)
* [ ] Distributed index data sharing between the nodes trough service API
* [ ] An idea to make unique gravatars for sites without favicons, because simpler to ident, comparing to ipv6
* [ ] An idea to make some visitors counters, like in good old times?

#### Feedback 

Please, feel free to share your ideas and bug reports [here](https://github.com/d47081/YGGo/issues) or use sources for your own implementations.

Have a good time.
