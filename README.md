### YGGo! - Open Source Web Search Engine

Written by inspiration to explore [Yggdrasil](https://yggdrasil-network.github.io) ecosystem, because of last [YaCy](https://yacy.net/) node there was discontinued.
This engine also could be useful for crawling regular websites, small business resources, local networks.

The project goal - simple interface, clear architecture and lightweight server requirement.

#### Online instances

* [http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo)
* [http://94.140.114.241/yggo/](http://94.140.114.241/yggo)

#### Screenshots

![Search page](https://github.com/YGGverse/YGGo/blob/main/media/search.png?raw=true)

https://github.com/YGGverse/YGGo/tree/main/media

#### License
* Engine sources [MIT License](https://github.com/YGGverse/YGGo/blob/main/LICENSE)
* Home page animation by [alvarotrigo](https://codepen.io/alvarotrigo/pen/GRvYNax)

#### Requirements

```
php8^
php-dom
php-pdo
php-curl
php-gd
php-mysql
sphinxsearch
```

#### Installation

* The webroot dir is `/public`
* Single configuration file placed here `/config/app.php.txt` and need to be configured and renamed to `/config/app.php`
* By the idea, script automaticaly generates database structure in `/storage` folder (where could be nice to collect other variative and tmp data - like logs, etc). Make sure storage folder writable.
* Set up the `/crontab/crawler.php` script for execution every the minute, but it mostly related of the configs and targetal network volume, there is no debug implemented yet, so let's silentize it by `/dev/null`
* Script has no MVC model, because of super simple. It's is just 2 files, and everything else stored incapsulated in `/library` classes.

#### Configuration

##### Crontab

```
0 * * * * indexer --all --rotate

0 0 * * * cd /YGGo/crontab && php cleaner.php > /dev/null 2>&1
* * * * * cd /YGGo/crontab && php crawler.php > /dev/null 2>&1
```

#### Roadmap / ideas

* [x] Web pages full text ranking search
* [x] Make search results pagination
* [x] Add robots.txt support (Issue #2)
* [ ] Improve yggdrasil links detection, add .ygg domain zone support
* [ ] Make page description visible - based on the cached content dump, when website description tag not available, add condition highlights
* [ ] Images search (basically implemented but requires testing and some performance optimization)
* [x] Index cleaner
* [ ] Crawl queue balancer, that depends from CPU available
* [ ] Implement smart queue algorithm that indexing new sites homepage in higher priority
* [ ] Implement database auto backup on crawl process completing
* [x] Add transactions to prevent data loss on DB crashes
* [ ] Distributed index data sharing between the nodes trough service API
* [x] An idea to make unique gravatars for sites without favicons, because simpler to ident, comparing to ipv6
* [ ] An idea to make some visitors counters, like in good old times?

#### Contributions

Please make a new master branch for each patch in your fork before create PR

```
git checkout master
git checkout -b my-pr-branch-name
```

See also: [SQLite tree](https://github.com/YGGverse/YGGo/tree/sqliteway)

#### Donate to contributors

* @d47081: [BTC](https://www.blockchain.com/explorer/addresses/btc/bc1qngdf2kwty6djjqpk0ynkpq9wmlrmtm7e0c534y) | [DOGE](https://dogechain.info/address/D5Sez493ibLqTpyB3xwQUspZvJ1cxEdRNQ) | Support our server by order [Linux VPS](https://www.yourserver.se/portal/aff.php?aff=610)

#### Feedback

Please, feel free to share your ideas and bug reports [here](https://github.com/YGGverse/YGGo/issues) or use sources for your own implementations.

Have a good time.
