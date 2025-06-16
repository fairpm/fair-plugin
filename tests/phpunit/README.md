# Instructions

## Setting up the dev environment

### Option 1: wp-env
1. Copy `wp-tests-config-sample.php` to `wp-tests-config.php`.
2. Ensure Docker is installed and running.
3. Run `npm i`
4. Run `npm run env start`
5. Run `npm run test:php:install-deps`

### Option 2: Local web server and MySQL database:
- Run `composer install`
- Create a new database using `mysql -u<username> -p` -> `CREATE DATABASE <database_name>;` -> `exit;`
  - Note: Don't use an existing database - the database is reset before every test run by default.
- Copy `wp-tests-config-sample.php` to `wp-tests-config.php`.
- Ensure that `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_HOST` in `wp-tests-config.php` match your setup.
- Run `./bin/install-wp-tests.sh` with the appropriate credentials.

## Running PHPUnit tests

### Single Site
```
npm run test:php
```
If you're not using wp-env, you can just run `composer run test`.

### Multisite
```
npm run test:php:multisite
```
If you're not using wp-env, you can just run `composer run test:multisite`.

## Running PHPUnit tests with line coverage

First, start the environment with xDebug:
```
npm run env start -- --xdebug=coverage
```
If you're not using wp-env, make sure the xDebug PHP module is installed and enabled.

### Single Site
```
npm run coverage:php:single
```
If you're not using wp-env, you can just run `composer run coverage:single`.

A coverage report will be available at `/tests/phpunit/coverage/html/single-site`.

### Multisite
```
npm run coverage:php:multisite
```
If you're not using wp-env, you can just run `composer run coverage:multisite`.

A coverage report will be available at `/tests/phpunit/coverage/html/multisite`.

### Single Site and Multisite
```
npm run coverage:php:full
```
If you're not using wp-env, you can just run `composer run coverage:full`.

A merged coverage report will be available at `/tests/phpunit/coverage/html/full`.
