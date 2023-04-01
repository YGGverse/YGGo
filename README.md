### YGGo! - Open Source Web Search Engine

Written by inspiration to research [Yggdrasil](https://yggdrasil-network.github.io) ecosystem, because of single [Yacy](https://yacy.net/) node was down.
Could be using for crawling regular websites, small business resources, local networks.

The goal - simple interface, clear architecture and lightheigth server requirements but effective content discovery.

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

#### TODO / ideas

* [x] Web pages full text ranking search
* [ ] Images search (basically implemented but requires testing and some performance optimization)
* [ ] Distributed index data sharing between the nodes trough service API
