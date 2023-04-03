<img src="https://d224n10qh3hhwu.cloudfront.net/github/hero-laravel.jpg" alt="Treblle for Laravel" align="center">

# Treblle for Laravel

[![Latest Version](https://img.shields.io/packagist/v/treblle/treblle-laravel)](https://packagist.org/packages/treblle/treblle-laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/treblle/treblle-laravel.svg?style=flat-square)](https://php.net)
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

<div align="center">
  <img src="https://treblle-github.s3.amazonaws.com/header.png"/>
</div>
<div align="center">

# Treblle

<a href="https://docs.treblle.com/en/integrations" target="_blank">Integrations</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="http://treblle.com/" target="_blank">Website</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://docs.treblle.com" target="_blank">Docs</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://blog.treblle.com" target="_blank">Blog</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://twitter.com/treblleapi" target="_blank">Twitter</a>
<span>&nbsp;&nbsp;•&nbsp;&nbsp;</span>
<a href="https://treblle.com/chat" target="_blank">Discord</a>
<br />

  <hr />
</div>

Treblle is a lightweight SDK that helps Engineering and Product teams build, ship & maintain REST based APIs faster.

## Features

<div align="center">
  <br />
  <img src="https://treblle-github.s3.amazonaws.com/features.png"/>
  <br />
  <br />
</div>

- [API Monitoring & Observability](https://www.treblle.com/features/api-monitoring-observability)
- [Auto-generated API Docs](https://www.treblle.com/features/auto-generated-api-docs)
- [API analytics](https://www.treblle.com/features/api-analytics)
- [Treblle API Score](https://www.treblle.com/features/api-quality-score)
- [API Lifecycle Collaboration](https://www.treblle.com/features/api-lifecycle)
- [Native Treblle Apps](https://www.treblle.com/features/native-apps)


## How Treblle Works
Once you’ve integrated a Treblle SDK in your codebase, this SDK will send requests and response data to your Treblle Dashboard.

In your Treblle Dashboard you get to see real-time requests to your API, auto-generated API docs, API analytics like how fast the response was for an endpoint, the load size of the response, etc.

Treblle also uses the requests sent to your Dashboard to calculate your API score which is a quality score that’s calculated based on the performance, quality, and security best practices for your API.

> Visit [https://docs.treblle.com](http://docs.treblle.com) for the complete documentation.

## Security

### Masking fields
Masking fields ensure certain sensitive data are removed before being sent to Treblle.

To make sure masking is done before any data leaves your server [we built it into all our SDKs](https://docs.treblle.com/en/security/masked-fields#fields-masked-by-default).

This means data masking is super fast and happens on a programming level before the API request is sent to Treblle. You can [customize](https://docs.treblle.com/en/security/masked-fields#custom-masked-fields) exactly which fields are masked when you’re integrating the SDK.

> Visit the [Masked fields](https://docs.treblle.com/en/security/masked-fields) section of the [docs](https://docs.sailscasts.com) for the complete documentation.


## Get Started

1. Sign in to [Treblle](https://app.treblle.com).
2. [Create a Treblle project](https://docs.treblle.com/en/dashboard/projects#creating-a-project).
3. [Setup the SDK](#install-the-SDK) for your platform.

### Install the SDK

Install Treblle for Laravel via Composer by running the following command in your terminal:

```sh
composer require treblle/treblle-laravel
```

You can get started with Treblle **directly from your Artisan console**. Just type in the following command in your
terminal:

```bash
php artisan treblle:start
```

The command guides you through a process and allows you to create an account, login to your existing account, create a
new project and get all the `.ENV` keys you need to start using Treblle.

You can also visit our website [https://app.treblle.com](https://app.treblle.com) and create a FREE account to get your API key and Project ID. Once
you have them, simply add them to your `.ENV` file:

```shell
TREBLLE_API_KEY=YOUR_API_KEY
TREBLLE_PROJECT_ID=YOUR_PROJECT_ID
```


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

> See the [docs](https://docs.treblle.com/en/integrations/laravel) for this SDK to learn more.

## Available SDKs

Treblle provides [open-source SDKs](https://docs.treblle.com/en/integrations) that let you seamlessly integrate Treblle with your REST-based APIs.

- [`treblle-laravel`](https://github.com/Treblle/treblle-laravel): SDK for Laravel
- [`treblle-php`](https://github.com/Treblle/treblle-php): SDK for PHP
- [`treblle-symfony`](https://github.com/Treblle/treblle-symfony): SDK for Symfony
- [`treblle-lumen`](https://github.com/Treblle/treblle-lumen): SDK for Lumen
- [`treblle-sails`](https://github.com/Treblle/treblle-sails): SDK for Sails
- [`treblle-adonisjs`](https://github.com/Treblle/treblle-adonisjs): SDK for AdonisJS
- [`treblle-fastify`](https://github.com/Treblle/treblle-fastify): SDK for Fastify
- [`treblle-directus`](https://github.com/Treblle/treblle-directus): SDK for Directus
- [`treblle-strapi`](https://github.com/Treblle/treblle-strapi): SDK for Strapi
- [`treblle-express`](https://github.com/Treblle/treblle-express): SDK for Express
- [`treblle-koa`](https://github.com/Treblle/treblle-koa): SDK for Koa
- [`treblle-go`](https://github.com/Treblle/treblle-go): SDK for Go
- [`treblle-ruby`](https://github.com/Treblle/treblle-ruby): SDK for Ruby on Rails
- [`treblle-python`](https://github.com/Treblle/treblle-python): SDK for Python/Django

> See the [docs](https://docs.treblle.com/en/integrations) for more on SDKs and Integrations.

## Other Packages

Besides the SDKs, we also provide helpers and configuration used for SDK
development. If you're thinking about contributing to or creating a SDK, have a look at the resources
below:

- [`treblle-utils`](https://github.com/Treblle/treblle-utils):  A set of helpers and
  utility functions useful for the JavaScript SDKs.
- [`php-utils`](https://github.com/Treblle/php-utils):   A set of helpers and
  utility functions useful for the PHP SDKs.

## Community 💙

First and foremost: **Star and watch this repository** to stay up-to-date.

Also, follow our [Blog](https://blog.treblle.com), and on [Twitter](https://twitter.com/treblleapi).

You can chat with the team and other members on [Discord](https://treblle.com/chat) and follow our tutorials and other video material at [YouTube](https://youtube.com/@treblle).

[![Treblle Discord](https://img.shields.io/badge/Treblle%20Discord-Join%20our%20Discord-F3F5FC?labelColor=7289DA&style=for-the-badge&logo=discord&logoColor=F3F5FC&link=https://treblle.com/chat)](https://treblle.com/chat)

[![Treblle YouTube](https://img.shields.io/badge/Treblle%20YouTube-Subscribe%20on%20YouTube-F3F5FC?labelColor=c4302b&style=for-the-badge&logo=YouTube&logoColor=F3F5FC&link=https://youtube.com/@treblle)](https://youtube.com/@treblle)

[![Treblle on Twitter](https://img.shields.io/badge/Treblle%20on%20Twitter-Follow%20Us-F3F5FC?labelColor=1DA1F2&style=for-the-badge&logo=Twitter&logoColor=F3F5FC&link=https://twitter.com/treblleapi)](https://twitter.com/treblleapi)

### How to contribute

Here are some ways of contributing to making Treblle better:

- **[Try out Treblle](https://docs.treblle.com/en/introduction#getting-started)**, and let us know ways to make Treblle better for you. Let us know here on [Discord](https://treblle.com/chat).
- Join our [Discord](https://treblle.com/chat) and connect with other members to share and learn from.
- Send a pull request to any of our [open source repositories](https://github.com/Treblle) on Github. Check the contribution guide on the repo you want to contribute to for more details about how to contribute. We're looking forward to your contribution!

### Contributors
<a href="https://github.com/Treblle/treblle-laravel/graphs/contributors">
  <p align="center">
    <img  src="https://contrib.rocks/image?repo=Treblle/treblle-laravel" alt="A table of avatars from the project's contributors" />
  </p>
</a>
