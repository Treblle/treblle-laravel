<img src="https://d224n10qh3hhwu.cloudfront.net/github/hero-laravel.jpg" alt="Treblle for Laravel" align="center">

# Treblle for Laravel

[![Latest Version](https://img.shields.io/packagist/v/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)
[![PHP Version][https://img.shields.io/packagist/php-v/juststeveking/php-sdk.svg?style=flat-square]][https://php.net]
[![Total Downloads](https://img.shields.io/packagist/dt/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)
[![MIT Licence](https://img.shields.io/packagist/l/treblle/treblle-laravel)](LICENSE.md)

Treblle makes it super easy to understand what’s going on with your APIs and the apps that use them. Just by adding
Treblle to your API out of the box you get:

* Real-time API monitoring and logging
* Auto-generated API docs with OAS support
* API analytics
* Quality scoring
* One-click testing
* API management on the go
* Supports Laravel Vapor and Laravel Octane
* and more...

## Requirements

* PHP 8.1+
* Laravel 9+

## Installation

Install Treblle for Laravel via [Composer](http://getcomposer.org/) by running the following command in your terminal:

```bash
composer require treblle/treblle-laravel
```

## Getting started

You can get started with Treblle **directly from your Artisan console**. Just type in the following command in your
terminal:

```bash
php artisan treblle:start
```

The command guides you through a process and allows you to create an account, login to your existing account, create a
new project and get all the .ENV keys you need to start using Treblle.

You can also visit our website <https://treblle.com> and create a FREE account to get your API key and Project ID. Once
you have to simply add them to your .ENV file:

```shell
TREBLLE_API_KEY=YOUR_API_KEY
TREBLLE_PROJECT_ID=YOUR_PROJECT_ID
```

## Enabling Treblle on your API

Open the **routes/api.php** and add the Treblle middleware to either a route group like so:

```php
Route::middleware(['treblle'])->group(function () {

  // YOUR API ROUTES GO HERE
  Route::prefix('samples')->group(function () {
    Route::get('{uuid}', [SampleController::class, 'view']);
    Route::post('store', [SampleController::class, 'store']);
  });

});
```

or to an individual route like so:

```php
Route::group(function () {
  Route::prefix('users')->group(function () {

    // IS LOGGED BY TREBLLE
    Route::get('{uuid}', [UserController::class, 'view'])->middleware('treblle');

    // IS NOT LOGGED BY TREBLLE
    Route::post('{uuid}/update', [UserController::class, 'update']);
  });
});
```

You're all set. Next time someone makes a request to your API you will see it in real-time on your Treblle dashboard
alongside other features like: auto-generated documentation, error tracking, analytics and API quality scoring.

## Configuration options

You can configure Treblle using just .ENV variables:

```shell
TREBLLE_IGNORED_ENV=local,dev,test
```

Define which environments Treblle should NOT LOG at all. By default, Treblle will log all environments except local, dev
and test. If you want to change that you can define your own ignored environments by using a comma separated list, or
allow all environments by leaving the value empty.

### Masked fields

Treblle **masks sensitive information** from both the request and response data as well as the request headers data
**before it even leaves your server**. The following parameters are automatically masked: password, pwd, secret,
password_confirmation, cc, card_number, ccv, ssn, credit_score.

You can customize this list by editing your configuration file. If you did not published your configuration file, run
this command first:

```bash
php artisan vendor:publish --tag=treblle-config
```

This will create a file at "config/treblle.php". Then, open this file and tweak the masked fields:

```php
return [
    // ...

    /*
     * Define which fields should be masked before leaving the server
     */
    'masked_fields' => [
        'password',
        'pwd',
        'secret',
        'password_confirmation',
        'cc',
        'card_number',
        'ccv',
        'ssn',
        'credit_score',
        'api_key',
    ],
];
```

## Support

If you have problems of any kind feel free to reach out via <https://treblle.com> or email hello@treblle.com, and we'll
do our best to help you out.

## License

Copyright 2022., Treblle Limited. Licensed under the MIT license:
http://www.opensource.org/licenses/mit-license.php
