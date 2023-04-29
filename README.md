### YGGo! - Distributed & Open Source Web Search Engine

_Проект присвячується захисникам міста Бахмут_

Written by inspiration to explore [Yggdrasil](https://yggdrasil-network.github.io) ecosystem, because of last [YaCy](https://yacy.net/) node there was discontinued.
This engine also could be useful for crawling regular websites, small business resources, local networks.

The project goal - simple interface, clear architecture and lightweight server requirement.

#### Overview

![Home page](https://github.com/YGGverse/YGGo/blob/main/media/main-sm.png?raw=true)

https://github.com/YGGverse/YGGo/tree/main/media

#### Online instances

* [http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo)
* [http://94.140.114.241/yggo/](http://94.140.114.241/yggo)

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

* The web root dir is `/public`
* Deploy the database using [MySQL Workbench](https://www.mysql.com/products/workbench) project presented in the `/database` folder
* Install [Sphinx Search Server](https://sphinxsearch.com)
* Configuration examples are placed at `/config` folder
* Make sure `/storage` folder is writable
* Set up the `/crontab` scripts by following [example](https://github.com/YGGverse/YGGo/blob/main/config/crontab.txt)

#### JSON API

Build third party applications / index distribution.

Could be enabled or disabled by `API_ENABLED` option

###### Address

```
/api.php
```

##### Search

Returns search results.

Could be enabled or disabled by `API_SEARCH_ENABLED` option

###### Request attributes

```
GET action=search  - required
GET query={string} - optional, search request, empty if not provided
GET page={int}     - optional, search results page, 1 if not provided
```

##### Hosts distribution

Returns node hosts collected with fields provided in `API_HOSTS_FIELDS` option.

Could be enabled or disabled by `API_HOSTS_ENABLED` option

###### Request attributes

```
GET action=hosts - required
```

##### Application manifest

Returns node information.

Could be enabled or disabled by `API_MANIFEST_ENABLED` option

###### Request attributes

```
GET action=manifest - required
```

#### Search textual filtering

https://sphinxsearch.com/docs/current.html#extended-syntax

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
* [x] JSON API
* [ ] Distributed index data sharing between the nodes trough service API
* [x] An idea to make unique gravatars for sites without favicons, because simpler to ident, comparing to ipv6
* [ ] An idea to make some visitors counters, like in good old times?

#### Contributions

Please make a new branch of master|sqliteway tree for each patch in your fork before create PR

```
git checkout master
git checkout -b my-pr-branch-name
```

See also: [SQLite tree](https://github.com/YGGverse/YGGo/tree/sqliteway)

#### Donate to contributors

* @d47081: [BTC](https://www.blockchain.com/explorer/addresses/btc/bc1qngdf2kwty6djjqpk0ynkpq9wmlrmtm7e0c534y) | [DOGE](https://dogechain.info/address/D5Sez493ibLqTpyB3xwQUspZvJ1cxEdRNQ) | Support our server by order [Linux VPS](https://www.yourserver.se/portal/aff.php?aff=610)

#### License
* Engine sources [MIT License](https://github.com/YGGverse/YGGo/blob/main/LICENSE)
* Home page animation by [alvarotrigo](https://codepen.io/alvarotrigo/pen/GRvYNax)

#### Feedback

Please, feel free to share your ideas and bug reports [here](https://github.com/YGGverse/YGGo/issues) or use sources for your own implementations.

Have a good time.
