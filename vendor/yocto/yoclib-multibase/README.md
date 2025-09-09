# yocLib - Multibase (PHP)

This yocLibrary enables your project to encode and decode Multibases in PHP.

## Status

[![PHP Composer](https://github.com/yocto/yoclib-multibase-php/actions/workflows/php.yml/badge.svg)](https://github.com/yocto/yoclib-multibase-php/actions/workflows/php.yml)
[![codecov](https://codecov.io/gh/yocto/yoclib-multibase-php/graph/badge.svg?token=CVJJGTZJ1X)](https://codecov.io/gh/yocto/yoclib-multibase-php)

## Installation

`composer require yocto/yoclib-multibase`

## Usage

### Encoding

```php
use YOCLIB\Multiformats\Multibase\Multibase;

$text = 'Hello world!';

$encodedString = Multibase::encode(Multibase::BASE16UPPER,$text);
```

### Decoding

```php
use YOCLIB\Multiformats\Multibase\Multibase;

$encodedString = 'F48656C6C6F20776F726C6421';

$text = Multibase::decode($encodedString);
```