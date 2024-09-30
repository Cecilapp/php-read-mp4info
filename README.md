# php-read-mp4info

> Fork of <https://github.com/clwu88/php-read-mp4info>

The format of the MP4 video file is analyzed using PHP to return rotation degree, width and height.

## Install

```bash
composer require cecil/php-read-mp4info
```

## Usage

```php
<?php

var_dump(\Mp4\Info::get('video.mp4'));
```

```php
array(3) {
    ["rotate"] => int(0)
    ["width"]  => int(960)
    ["height"] => int(544)
}
```
