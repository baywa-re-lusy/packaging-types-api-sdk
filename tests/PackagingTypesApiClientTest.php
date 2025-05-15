<?php

namespace BayWaReLusy\PackagingTypesAPI\Test;

use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeEntity;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypesApiClient;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypesApiException;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeSortField;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeEntity\Category;

class PackagingTypesApiClientTest extends TestCase
{
    protected PackagingTypesApiClient $instance;
    protected MockObject $tokenCacheMock;
    protected MockInterface $packagingTypesCacheMock;
    protected MockObject $cacheMock;
    protected MockObject $loggerMock;
    protected MockObject $consoleMock;
    protected MockObject $uriFactoryMock;
    protected MockHandler $guzzleMockHandler;
    protected array $httpRequestHistoryContainer = [];

    protected function setUp(): void
    {
        $this->tokenCacheMock          = $this->createMock(CacheItemPoolInterface::class);
        $this->loggerMock              = $this->createMock(LoggerInterface::class);
        $this->consoleMock             = $this->createMock(OutputInterface::class);
        $this->packagingTypesCacheMock = Mockery::mock(CacheItemPoolInterface::class);
        $this->uriFactoryMock          = $this->createMock(UriFactoryInterface::class);

        $this->guzzleMockHandler = new MockHandler();
        $handlerStack            = HandlerStack::create($this->guzzleMockHandler);

        $handlerStack->push(Middleware::history($this->httpRequestHistoryContainer));

        $this->instance = new PackagingTypesApiClient(
            'https://api.domain.com',
            'https://api.domain.com/token',
            'client-id',
            'client-secret',
            $this->tokenCacheMock,
            $this->packagingTypesCacheMock,
            new Psr17Factory(),
            new Psr17Factory(),
            new HttpClient(['handler' => $handlerStack]),
            $this->loggerMock
        );
    }

    /**
     * Test the GET /packaging-types call.
     * -> Packaging Types are fetched from the cache
     * -> No call to token endpoint or token cache necessary
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_PackagingTypesCacheHit(): void
    {
        // If the Packaging Types cache hits, there is no call to the token cache
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('getItem');

        // Mock the cache hit for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn([
                (new PackagingTypeEntity())
                    ->setId(Uuid::fromString('c84056a1-8d36-46c4-ae15-e3cb3db18ed2'))
                    ->setCategory(Category::PALLET)
                    ->setName('Module Pallet 240cm')
                    ->setShortName('MPal 240')
                    ->setTransporeonId('MODULE_PALLET_240')
                    ->setLength(240)
                    ->setWidth(115)
                    ->setHeight(125)
                    ->setWeight(50),
                (new PackagingTypeEntity())
                    ->setId(Uuid::fromString('05991cfa-84a4-4c7f-9486-7d25c6119238'))
                    ->setCategory(Category::PARCEL)
                    ->setName('Box')
                    ->setTransporeonId('BOX')
                    ->setLength(40)
                    ->setWidth(30)
                    ->setHeight(30)
                    ->setWeight(0),
            ]);

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingTypes')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Execute the call
        $packagingTypes = $this->instance->getPackagingTypes();

        // Verify resulting users
        $this->validatePackagingTypesProperties($packagingTypes);
    }

    /**
     * Test the GET /packaging-types call.
     * -> Packaging Types could be fetched from the cache, but the refreshCache option is enabled
     * -> No call to token endpoint or token cache necessary
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_PackagingTypesCacheRefresh(): void
    {
        $this->instance->setConsole($this->consoleMock);
        $this->consoleMock
            ->expects($this->exactly(4))
            ->method('writeln');

        // If the Packaging Types cache hits, there is no call to the token cache
        $this->mockTokenCacheHit();

        // Users Cache is bypassed and cache entry is refreshed
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('isHit');

        $this->mockCachingOfPackagingTypes($packagingTypesCacheItemMock);

        // Mock the packaging types response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/packaging-types.json'))
        );

        // Execute the call
        $packagingTypes = $this->instance->getPackagingTypes(PackagingTypeSortField::ID, false, true);

        // Verify resulting users
        $this->validatePackagingTypesProperties($packagingTypes);
    }

    /**
     * Test the GET /packaging-types call.
     * -> Packaging Types are not fetched from the cache
     * -> Token found in the token cache
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_PackagingTypesCacheMiss_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the packaging types
        $this->mockCacheMissForPackagingTypesCall();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/packaging-types.json'))
        );

        // Execute the call
        $packagingTypes = $this->instance->getPackagingTypes();

        // Verify resulting users
        $this->validatePackagingTypesProperties($packagingTypes);

        // Verify if HTTP requests have been made correctly
        $this->validatePackagingTypesRequest(0);
    }

    /**
     * Test the GET /packaging-types call.
     * -> Packaging Types are not fetched from the cache
     * -> Token not found in the token cache
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_PackagingTypesCacheMiss_TokenCacheMiss(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheMiss();

        // Mock the cache miss for the users
        $this->mockCacheMissForPackagingTypesCall();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(
            new Response(200, [], '{"access_token": "access-token", "expires_in": "60"}'),
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/packaging-types.json'))
        );

        // Execute the call
        $packagingTypes = $this->instance->getPackagingTypes();

        // Verification
        $this->validatePackagingTypesProperties($packagingTypes);

        // Verify if HTTP requests have been made correctly
        $this->validateTokenRequest();
        $this->validatePackagingTypesRequest(1);
    }

    /**
     * Test the GET /packaging-types call.
     * -> Token cache throws exception
     * -> No call to Packaging Types cache or API
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_CacheError(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockCacheException();

        // Mock the cache hit for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingTypes')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingTypes();
    }

    /**
     * Test the GET /packaging-types call.
     * -> Token cache miss
     * -> Token request call throws exception
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_TokenRequestException(): void
    {
        // Mock the Cache hit for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->never())
            ->method('expiresAfter');
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('packagingTypesApiToken')
            ->willReturn($cacheItemMock);
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache hit for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingTypes')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(new ClientException('token request error'));

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingTypes();
    }

    /**
     * Test the GET /packaging-types call.
     * -> Token cache hit
     * -> Packaging Types GET request throws exception
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingTypes_PackagingTypesGetException_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingTypes')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(new ClientException('user get exception'));

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingTypes();
    }

    /**
     * Test the GET /packaging-types/<Id> call.
     * -> Packaging Type is fetched from the cache
     * -> No call to token endpoint or token cache necessary
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_PackagingTypeCacheHit(): void
    {
        // If the User cache hits, there is no call to the token cache
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('getItem');

        // Mock the cache hit for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn(
                (new PackagingTypeEntity())
                    ->setId(Uuid::fromString('c84056a1-8d36-46c4-ae15-e3cb3db18ed2'))
                    ->setCategory(Category::PALLET)
                    ->setName('Module Pallet 240cm')
                    ->setShortName('MPal 240')
                    ->setTransporeonId('MODULE_PALLET_240')
                    ->setLength(240)
                    ->setWidth(115)
                    ->setHeight(125)
                    ->setWeight(50)
            );

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Execute the call
        $user = $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify resulting users
        $this->validatePackagingTypeProperties($user);
    }

    /**
     * Test the GET /packaging-types/<id> call.
     * -> Packaging Type isn't fetched from the cache
     * -> Token found in the token cache
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_PackagingTypeCacheMiss_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the users
        $this->mockCacheMissForPackagingTypeCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/packaging-type.json'))
        );

        // Execute the call
        $packagingType = $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify result(s) and request(s)
        $this->validatePackagingTypeProperties($packagingType);
        $this->validatePackagingTypeRequest(0);
    }

    /**
     * Test the GET /packaging-types/<id> call.
     * -> Packaging Type isn't fetched from the cache
     * -> Token not found in the token cache
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_PackagingTypeCacheMiss_TokenCacheMiss(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheMiss();

        // Mock the cache miss for the users
        $this->mockCacheMissForPackagingTypeCall();

        // Mock the users response
        $this->guzzleMockHandler->append(
            new Response(200, [], '{"access_token": "access-token", "expires_in": "60"}'),
            new Response(200, [], (string)file_get_contents(__DIR__ . '/_files/packaging-type.json'))
        );

        // Execute the call
        $packagingType = $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');

        // Verify result(s) and request(s)
        $this->validatePackagingTypeProperties($packagingType);
        $this->validateTokenRequest();
        $this->validatePackagingTypeRequest(1);
    }

    /**
     * Test the GET /packaging-types/<id> call.
     * -> Token cache throws exception
     * -> No call to packaging types cache or API
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_CacheError(): void
    {
        // Mock the Cache exception for the access token call
        $this->mockCacheException();

        // Mock the cache miss for the user
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * Test the GET /packaging-types/<id> call.
     * -> Token cache miss
     * -> Token request call throws exception
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_TokenRequestException(): void
    {
        // Mock the Cache miss for the access token call
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->never())
            ->method('expiresAfter');
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('packagingTypesApiToken')
            ->willReturn($cacheItemMock);
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');

        // Mock the cache hit for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(new ClientException('token request error'));

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * Test the GET /packaging-types/<id> call.
     * -> Token cache hit
     * -> Packaging Type GET request throws exception
     *
     * @return void
     * @throws PackagingTypesApiException
     * @throws Exception
     */
    public function testGetPackagingType_PackagingTypeGetException_TokenCacheHit(): void
    {
        // Mock the Cache hit for the access token call
        $this->mockTokenCacheHit();

        // Mock the cache miss for the users
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('set');
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->never();

        // Mock the packaging types response
        $this->guzzleMockHandler->append(new ClientException('packaging type get exception'));

        $this->expectException(PackagingTypesApiException::class);

        // Execute the call
        $this->instance->getPackagingType('c84056a1-8d36-46c4-ae15-e3cb3db18ed2');
    }

    /**
     * @param PackagingTypeEntity[] $packagingTypes
     * @return void
     */
    protected function validatePackagingTypesProperties(array $packagingTypes): void
    {
        // Check the number of returned users
        $this->assertCount(2, $packagingTypes);

        // Check that the results are User entities
        $this->assertInstanceOf(PackagingTypeEntity::class, $packagingTypes[0]);
        $this->assertInstanceOf(PackagingTypeEntity::class, $packagingTypes[1]);

        // Check if the properties are set correctly
        $this->assertEquals('c84056a1-8d36-46c4-ae15-e3cb3db18ed2', $packagingTypes[0]->getId());
        $this->assertEquals('05991cfa-84a4-4c7f-9486-7d25c6119238', $packagingTypes[1]->getId());

        $this->assertEquals(Category::PALLET, $packagingTypes[0]->getCategory());
        $this->assertEquals(Category::PARCEL, $packagingTypes[1]->getCategory());

        $this->assertEquals('Module Pallet 240cm', $packagingTypes[0]->getName());
        $this->assertEquals('Box', $packagingTypes[1]->getName());

        $this->assertEquals('MPal 240', $packagingTypes[0]->getShortName());
        $this->assertNull($packagingTypes[1]->getShortName());

        $this->assertEquals('MODULE_PALLET_240', $packagingTypes[0]->getTransporeonId());
        $this->assertEquals('BOX', $packagingTypes[1]->getTransporeonId());

        $this->assertEquals(240, $packagingTypes[0]->getLength());
        $this->assertEquals(40, $packagingTypes[1]->getLength());

        $this->assertEquals(115, $packagingTypes[0]->getWidth());
        $this->assertEquals(30, $packagingTypes[1]->getWidth());

        $this->assertEquals(125, $packagingTypes[0]->getHeight());
        $this->assertEquals(30, $packagingTypes[1]->getHeight());

        $this->assertEquals(50, $packagingTypes[0]->getWeight());
        $this->assertEquals(0, $packagingTypes[1]->getWeight());
    }

    protected function validatePackagingTypeProperties(PackagingTypeEntity $packagingType): void
    {
        $this->assertEquals('c84056a1-8d36-46c4-ae15-e3cb3db18ed2', $packagingType->getId());
        $this->assertEquals(Category::PALLET, $packagingType->getCategory());
        $this->assertEquals('Module Pallet 240cm', $packagingType->getName());
        $this->assertEquals('MPal 240', $packagingType->getShortName());
        $this->assertEquals('MODULE_PALLET_240', $packagingType->getTransporeonId());
        $this->assertEquals(240, $packagingType->getLength());
        $this->assertEquals(115, $packagingType->getWidth());
        $this->assertEquals(125, $packagingType->getHeight());
        $this->assertEquals(50, $packagingType->getWeight());
    }

    protected function validateTokenRequest(): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[0]['request']);
        $this->assertEquals('POST', $this->httpRequestHistoryContainer[0]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[0]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[0]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[0]['request']->getUri()->getHost());
        $this->assertEquals('/token', $this->httpRequestHistoryContainer[0]['request']->getUri()->getPath());
        $this->assertEquals(
            'application/json',
            $this->httpRequestHistoryContainer[0]['request']->getHeader('Accept')[0]
        );
        $this->assertEquals(
            'application/x-www-form-urlencoded',
            $this->httpRequestHistoryContainer[0]['request']->getHeader('Content-Type')[0]
        );
    }

    protected function validatePackagingTypesRequest(int $nb): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[$nb]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[$nb]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[$nb]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getHost());
        $this->assertEquals('/packaging-types', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getPath());
        $this->assertEquals(
            'application/ld+json',
            $this->httpRequestHistoryContainer[$nb]['request']->getHeader('Accept')[0]
        );
    }

    protected function validatePackagingTypeRequest(int $nb): void
    {
        $this->assertInstanceOf(RequestInterface::class, $this->httpRequestHistoryContainer[$nb]['request']);
        $this->assertEquals('GET', $this->httpRequestHistoryContainer[$nb]['request']->getMethod());
        $this->assertInstanceOf(UriInterface::class, $this->httpRequestHistoryContainer[$nb]['request']->getUri());
        $this->assertEquals('https', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getScheme());
        $this->assertEquals('api.domain.com', $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getHost());
        $this->assertEquals(
            '/packaging-types/c84056a1-8d36-46c4-ae15-e3cb3db18ed2',
            $this->httpRequestHistoryContainer[$nb]['request']->getUri()->getPath()
        );
        $this->assertEquals(
            'application/ld+json',
            $this->httpRequestHistoryContainer[$nb]['request']->getHeader('Accept')[0]
        );
    }

    protected function mockTokenCacheMiss(): void
    {
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with('access-token')
            ->willReturnSelf();
        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50)
            ->willReturnSelf();
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('packagingTypesApiToken')
            ->willReturn($cacheItemMock);
        $this->tokenCacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);
    }

    protected function mockTokenCacheHit(): void
    {
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn('access-token');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with('packagingTypesApiToken')
            ->willReturn($cacheItemMock);
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');
    }

    protected function mockCacheMissForPackagingTypesCall(): void
    {
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $this->mockCachingOfPackagingTypes($packagingTypesCacheItemMock);
    }

    protected function mockCacheMissForPackagingTypeCall(): void
    {
        $packagingTypesCacheItemMock = $this->createMock(CacheItemInterface::class);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->callback(function ($value) {
                return
                    $value->getId()->toString() === 'c84056a1-8d36-46c4-ae15-e3cb3db18ed2' &&
                    $value->getCategory() === Category::PALLET &&
                    $value->getName() === 'Module Pallet 240cm' &&
                    $value->getShortName() === 'MPal 240' &&
                    $value->getTransporeonId() === 'MODULE_PALLET_240' &&
                    $value->getLength() === 240 &&
                    $value->getWidth() === 115 &&
                    $value->getHeight() === 125 &&
                    $value->getWeight() === 50.0;
            }))
            ->willReturnSelf();
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(0);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->once()
            ->with($packagingTypesCacheItemMock);
    }

    protected function mockCachingOfPackagingTypes(MockObject $packagingTypesCacheItemMock): void
    {
        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->callback(function ($value) {
                return
                    $value[0]->getId()->toString() === 'c84056a1-8d36-46c4-ae15-e3cb3db18ed2' &&
                    $value[0]->getCategory() === Category::PALLET &&
                    $value[0]->getName() === 'Module Pallet 240cm' &&
                    $value[0]->getShortName() === 'MPal 240' &&
                    $value[0]->getTransporeonId() === 'MODULE_PALLET_240' &&
                    $value[1]->getLength() === 40 &&
                    $value[1]->getWidth() === 30 &&
                    $value[1]->getHeight() === 30 &&
                    $value[1]->getWeight() === 0.0;
            }))
            ->willReturnSelf();

        $packagingTypesCacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(0)
            ->willReturn($packagingTypesCacheItemMock);
        $packagingTypesCacheItemMock
            ->expects($this->never())
            ->method('get');

        $user1CacheItemMock = $this->createMock(CacheItemInterface::class);
        $user1CacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(0)
            ->willReturn($user1CacheItemMock);
        $user1CacheItemMock
            ->expects($this->never())
            ->method('get');
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->once()
            ->with($user1CacheItemMock);

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_c84056a1-8d36-46c4-ae15-e3cb3db18ed2')
            ->andReturns($user1CacheItemMock);

        $user2CacheItemMock = $this->createMock(CacheItemInterface::class);
        $user2CacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(0)
            ->willReturn($user2CacheItemMock);
        $user2CacheItemMock
            ->expects($this->never())
            ->method('get');
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->once()
            ->with($user2CacheItemMock);

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingType_05991cfa-84a4-4c7f-9486-7d25c6119238')
            ->andReturns($user2CacheItemMock);

        $this->packagingTypesCacheMock
            ->shouldReceive('getItem')
            ->once()
            ->with('packagingTypes')
            ->andReturns($packagingTypesCacheItemMock);
        $this->packagingTypesCacheMock
            ->shouldReceive('save')
            ->once()
            ->with($packagingTypesCacheItemMock);
    }

    protected function mockCacheException(): void
    {
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $cacheItemMock
            ->expects($this->never())
            ->method('isHit');
        $cacheItemMock
            ->expects($this->never())
            ->method('set');
        $cacheItemMock
            ->expects($this->never())
            ->method('get');

        $this->tokenCacheMock
            ->expects($this->once())
            ->method('getItem')
            ->willThrowException(new InvalidArgumentException('cache error'));
        $this->tokenCacheMock
            ->expects($this->never())
            ->method('save');
    }

    public function tearDown(): void
    {
        Mockery::close();
    }
}
