BayWa r.e. Packaging Types API SDK
==================================

This SDK can be used to retrieve Packaging Types, optionally filtered by subsidiaries or Transporeon ID.

All dependencies injected into the constructor are PSR-compatible:
* Cache : https://www.php-fig.org/psr/psr-6/
* HTTP Client : https://www.php-fig.org/psr/psr-18/
* HTTP Messages : https://www.php-fig.org/psr/psr-7/
* Logger : https://www.php-fig.org/psr/psr-3/
* HTTP Factories : https://www.php-fig.org/psr/psr-17/

## Installation

```shell
composer require baywa-re-lusy/packaging-types-api-sdk
```

## Usage

```php
use Laminas\Cache\Storage\Adapter\Apcu;

$tokenCache  = new \Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator(new Apcu());
$resultCache = new \Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator(new Apcu());
$httpFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
$httpClient  = new \GuzzleHttp\Client();

$packagingTypesApiClient = new \BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypesApiClient(
    "<URL to Users API>",
    "<URL to Token API Endpoint>",
    "<Client ID>",
    "<Client Secret>",
    $tokenCache,
    $resultCache,
    $httpFactory,
    $httpFactory,
    $httpClient    
);

$packagingTypes = $packagingTypesApiClient->getPackagingTypes();
$packagingType  = $packagingTypesApiClient->getPackagingType('<id>');
$packagingType  = $packagingTypesApiClient->findOneByTransporeonId('<Transporeon ID>');
```

## Cache Refresh via Console commands

This SDK contains a Symfony Console command to refresh the Packaging Type cache. You can include the Console command
into your application:

```php
$cliApp = new \Symfony\Component\Console\Application();
$cliApp->add(new \BayWaReLusy\PackagingTypesAPI\SDK\Console\RefreshPackagingTypesCache($packagingTypesApiClient));
```

And then run the Console commands with:

```shell
./console packaging-types-api-sdk:refresh-packaging-types-cache
```
