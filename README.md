# Treblle for Laravel

[![Latest Version](https://img.shields.io/packagist/v/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)
[![MIT Licence](https://img.shields.io/packagist/l/treblle/treblle-laravel)](LICENSE.md)

Treblle makes it super easy to understand what’s going on with your APIs and the apps that use them. Just by adding Treblle to your API out of the box you get:
* Real-time API monitoring and logging
* Auto-generated API docs with OAS support
* API analytics
* Quality scoring
* One-click testing
* API managment on the go
* and more...

## Requirements
* PHP 7.2+
* Laravel 6+

## Dependencies
* [`laravel/framework`](https://packagist.org/packages/laravel/framework)
* [`guzzlehttp/guzzle`](https://packagist.org/packages/guzzlehttp/guzzle)
* [`nesbot/carbon`](https://packagist.org/packages/nesbot/carbon)

## Installation
Install Treblle for Laravel via [Composer](http://getcomposer.org/) by running the following command in your terminal:

```bash
$ composer require treblle/treblle-laravel
```

## Getting started
You can get started with Treblle **directly from your Artisan console**. Just type in the following command in your terminal:
 
```bash
$ php artisan treblle:start
```
The command guides you through a process and allows you to create an account, login to your existing account, create a new project and get all the .ENV keys you need to start using Treblle.

You can also visit our website <https://treblle.com> and create a FREE account to get your API key and Project ID. Once you have to simply add them to your .ENV file:

```shell
TREBLLE_API_KEY=_YOUR_API_KEY_
TREBLLE_PROJECT_ID=_YOUR_PROJECT_ID
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

You'r all set. Next time someone makes a request to your API you will see it in real-time on your Treblle dashboard alongside other features like: auto-generated documentation, error tracking, analytics and API quality scoring.

## Configuration options
You can configure Treblle using just .ENV variables:

```shell
TREBLLE_IGNORED_ENV=local,dev,test
```
By default Treblle will ignore requests made on the **local** environment. If you want to change that you can define your own ignored environments, by using a comma separated list, or allow all environments by leaving the value empty.

```shell
TREBLLE_MASKED_FIELDS=email,user_address,phone_number
```
Treblle **masks sensitive information** from both the request and response data **before it even leaves your server**. The following parameters are automatically masked: password, pwd, secret, password_confirmation, cc, card_number, ccv, ssn, credit_score. You can add your own custom list by simply defining them as a comma separated list in the variable above.


## Support
If you have problems of any kind feel free to reach out via <https://treblle.com> or email vedran@treblle.com and we'll do our best to help you out.

## License
Copyright 2021, Treblle Limited. Licensed under the MIT license:
http://www.opensource.org/licenses/mit-license.php
