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
GET type={string}  - optional, search type, image|default or empty
GET page={int}     - optional, search results page, 1 if not provided
GET mode=SphinxQL  - optional, enable extended SphinxQL syntax
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

##### Default constructions

```
operator OR:

hello | world

operator MAYBE:

hello MAYBE world

operator NOT:

hello -world

strict order operator (aka operator "before"):

aaa << bbb << ccc

exact form modifier:

raining =cats and =dogs

field-start and field-end modifier:

^hello world$

keyword IDF boost modifier:

boosted^1.234 boostedfieldend$^1.234

```

##### Extended syntax

https://sphinxsearch.com/docs/current.html#extended-syntax

Could be enabled with following attributes

```
GET m=SphinxQL
```

#### Roadmap

##### Basic features

* [x] Web pages full text ranking search
* [x] Images search with safe proxy preview support
* [x] Extended syntax support
* [x] Flexible settings compatible with IPv4/IPv6 networks

##### UI

* [x] CSS only, JS-less interface
* [x] Unique ident icons for sites without favicons
* [ ] Results with found matches highlight
* [ ] Content genre tabs (#1)
* [ ] The time machine feature by content history cache preview
* [ ] Link clicks counter

##### API

* [ ] Index API
  + [x] Manifest
  + [x] Search
  + [x] Hosts
  + [ ] Pages
  + [ ] Images
* [ ] Context advertising API

##### Crawler

* [x] Auto crawl links by regular expression rules
* [x] Robots.txt / robots meta tags support (#2)
* [x] Specific rules configuration for every host
* [x] Deprecated index auto cleaner
* [x] Auto stop crawling on disk quota reached
* [x] Transactions support to prevent data loss on queue failures
* [x] Distributed index crawling between YGGo nodes trough manifest API
* [ ] Indexing new sites homepage in higher priority
* [ ] Redirect codes extended processing
* [ ] Palette image index / filter
* [ ] Crawl queue balancer, that depends of CPU available

##### Other

* [ ] Administrative panel for useful index moderation


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
