_Project archived. Please, visit [Yo!](https://github.com/YGGverse/Yo) - the next generation of YGGo project based on Manticore search._

### YGGo! - Distributed Web Search Engine

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

Written by inspiration to explore [Yggdrasil](https://github.com/yggdrasil-network) ecosystem.
Engine could be useful for crawling regular websites, small business resources, local networks.

The project goal - simple interface, clear architecture and lightweight server requirement.

#### Overview

![Home page](https://github.com/YGGverse/YGGo/blob/main/media/main-sm.png?raw=true)

https://github.com/YGGverse/YGGo/tree/main/media

#### Online instances

* `http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggo/`

#### Database snaps

* 17-09-2023 `http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggtracker/en/torrent/15`

#### Requirements

```
php8^
php-dom
php-xml
php-pdo
php-curl
php-gd
php-mbstring
php-zip
php-mysql
php-memcached
memcached
sphinxsearch
```

#### Installation

* `git clone https://github.com/YGGverse/YGGo.git`
* `cd YGGo`
* `composer install`

### Setup
* Server configuration `/example/environment`
* The web root dir is `/src/public`
* Deploy the database using [MySQL Workbench](https://www.mysql.com/products/workbench) project presented in the `/database` folder
* Install [Sphinx Search Server](https://sphinxsearch.com)
* Configuration examples presented at `/config` folder
* Make sure `/src/storage/cache`, `/src/storage/tmp`, `/src/storage/snap` folders are writable
* Set up the `/src/crontab` by following [example](https://github.com/YGGverse/YGGo/blob/main/config/crontab.txt)
* To start crawler, add at least one initial URL using search form or CLI

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
GET type={string}  - optional, filter mime type of available or empty
GET page={int}     - optional, search results page, 1 if not provided
GET mode=SphinxQL  - optional, enable extended SphinxQL syntax
```

##### Hosts distribution

Returns hosts collected with fields provided in `API_HOSTS_FIELDS` option.

Could be enabled or disabled by `API_HOSTS_ENABLED` option

###### Request attributes

```
GET action=hosts - required
```

##### Application manifest

Returns node information for other nodes that have same `CRAWL_MANIFEST_API_VERSION` and `DEFAULT_HOST_URL_REGEXP` conditions.

Could be enabled or disabled by `API_MANIFEST_ENABLED` option

###### Request attributes

```
GET action=manifest - required
```

#### Search textual filtering

##### Default constructions

```
word prefix:

yg*

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
  + [x] Sphinx
* [x] Unlimited content MIME crawling
* [x] Flexible settings compatible with IPv4/IPv6 networks
* [x] Extended search syntax support
* [x] Compressed page history snaps with multi-provider storage sync
  + [x] Local (unlimited locations)
  + [x] Remote FTP (unlimited mirrors)
  + [x] Privacy-oriented downloads counting, traffic controls

##### UI

* [x] CSS only, JS-less interface
* [x] Unique host ident icons
* [x] Content MIME tabs (#1)
* [x] Page index explorer
  + [x] Meta
  + [x] Snaps history
  + [x] Referrers
* [x] Top hosts page
* [ ] Safe media preview
* [ ] Results with found matches highlight
* [ ] The time machine feature by content snaps history

##### API

* [ ] Index API
  + [x] Manifest
  + [x] Search
  + [x] Hosts
  + [ ] Snaps
* [ ] Context advertising API

##### Crawler

* [x] Auto crawl links by regular expression rules
  + [x] Pages
  + [x] Manifests
* [x] Robots.txt / robots meta tags support (#2)
* [x] Specific rules configuration for every host
* [x] Auto stop crawling on disk quota reached
* [x] Transactions support to prevent data loss on queue failures
* [x] Distributed index crawling between YGGo nodes trough manifest API
* [x] MIME Content-type settings
* [x] Ban non-condition links to prevent extra requests
* [x] Debug log
* [x] Index homepages and shorter URI with higher priority
* [x] Collect target location links on page redirect available
* [x] Collect referrer pages (redirects including)
* [x] URL aliasing support on PR calculation
* [ ] Host page DOM elements collecting by CSS selectors
  * [ ] Custom settings for each host
* [ ] XML Feeds support
  + [x] Sitemap
  + [ ] RSS
  + [ ] Atom
* [ ] Palette image index / filter
* [ ] Crawl queue balancer, that depends of CPU available
* [x] Networks integration
  + [x] [yggdrasil](https://github.com/yggdrasil-network)
    + [x] [YGGstate](https://github.com/YGGverse/YGGstate) (unlimited nodes)
      + [x] DB
      + [ ] API

##### Cleaner

* [x] Banned pages reset by timeout
* [x] DB tables optimization

##### CLI

_*CLI interface still under construction, use it for your own risk!_

* [x] help
* [x] db
  * [x] optimize
  [x] crontab
  * [x] crawl
  * [x] clean
* [ ] hostSetting
  + [x] get
  + [x] set
  + [ ] list
  + [ ] delete
  + [ ] flush
* [x] hostPage
  + [x] add
  + [x] rank
    + [x] reindex
* [x] hostPageSnap
  + [x] repair
    + [x] db
    + [x] fs
  + [ ] reindex
  + [ ] truncate

##### Other

* [ ] Administrative panel for useful index moderation
* [ ] Deployment tools
* [ ] Testing
* [ ] Documentation

#### Contributions

Please make a new branch of main|sqliteway tree for each patch in your fork before create PR

```
git checkout main
git checkout -b my-pr-branch-name
```

See also: [SQLite tree](https://github.com/YGGverse/YGGo/tree/sqliteway)

#### Donate to contributors

* @d47081: [BTC](https://www.blockchain.com/explorer/addresses/btc/bc1qngdf2kwty6djjqpk0ynkpq9wmlrmtm7e0c534y) | [LTC](https://live.blockcypher.com/ltc/address/LUSiqzKsfB1vBLvpu515DZktG9ioKqLyj7) | [XMR](835gSR1Uvka19gnWPkU2pyRozZugRZSPHDuFL6YajaAqjEtMwSPr4jafM8idRuBWo7AWD3pwFQSYRMRW9XezqrK4BEXBgXE) | [ZEPH](ZEPHsADHXqnhfWhXrRcXnyBQMucE3NM7Ng5ZVB99XwA38PTnbjLKpCwcQVgoie8EJuWozKgBiTmDFW4iY7fNEgSEWyAy4dotqtX) | Support our server by order [Linux VPS](https://www.yourserver.se/portal/aff.php?aff=610)

#### License

* Engine sources [MIT License](https://github.com/YGGverse/YGGo/blob/main/LICENSE)
* Home page animation by [alvarotrigo](https://codepen.io/alvarotrigo/pen/GRvYNax)
* CLI logo by [patorjk.com](https://patorjk.com/software/taag/#p=display&f=Slant&t=YGGo!)
* Transliteration by [php-translit](https://github.com/ashtokalo/php-translit)
* Identicons by [jdenticon](https://github.com/dmester/jdenticon-php)

#### Feedback

Feel free to [share](https://github.com/YGGverse/YGGo/issues) your ideas and bug reports!

#### Community

* [Mastodon](https://mastodon.social/@YGGverse)
* [[matrix]](https://matrix.to/#/#YGGo:matrix.org)

#### See also

* [Yo! Micro Web Crawler in PHP & Manticore](https://github.com/YGGverse/Yo)
* [YGGwave ~ The Radio Catalog](https://github.com/YGGverse/YGGwave)
* [YGGstate - Yggdrasil Network Analytics](https://github.com/YGGverse/YGGstate)
* [YGGtracker - BitTorrent Tracker and Catalog for Yggdrasil](https://github.com/YGGverse/YGGtracker)