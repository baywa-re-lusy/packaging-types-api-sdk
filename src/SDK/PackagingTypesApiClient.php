<?php

namespace BayWaReLusy\PackagingTypesAPI\SDK;

use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypeEntity\Category;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientInterface as HttpClient;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface as Console;
use Throwable;

class PackagingTypesApiClient
{
    protected const CACHE_KEY_PACKAGING_TYPES = 'packagingTypes';
    protected const CACHE_KEY_PACKAGING_TYPE  = 'packagingType_%s';
    protected const CACHE_KEY_API_TOKEN       = 'packagingTypesApiToken';
    protected const CACHE_TTL                 = 0;
    protected const PACKAGING_TYPES_URI       = '/packaging-types';

    protected ?string $accessToken = null;
    protected ?Console $console    = null;

    public function __construct(
        protected string $apiUrl,
        protected string $tokenUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected CacheItemPoolInterface $cache,
        protected RequestFactoryInterface $requestFactory,
        protected UriFactoryInterface $uriFactory,
        protected HttpClient $httpClient,
        protected ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param Console $console
     * @return self
     */
    public function setConsole(Console $console): self
    {
        $this->console = $console;
        return $this;
    }

    /**
     * Get a token for the Packaging Types API.
     *
     * @throws PackagingTypesApiException
     */
    protected function loginToAuthServer(): void
    {
        try {
            // Search for API token in Token Cache
            $cachedToken = $this->cache->getItem(self::CACHE_KEY_API_TOKEN);

            // If the cached Token is valid
            if ($cachedToken->isHit()) {
                $accessToken = $cachedToken->get();
            } else {
                // If the cached Token isn't valid, generate a new one
                $tokenRequest = $this->requestFactory->createRequest('POST', $this->tokenUrl);
                $tokenRequest = $tokenRequest->withHeader('Accept', 'application/json');
                $tokenRequest = $tokenRequest->withHeader('Content-Type', 'application/x-www-form-urlencoded');

                $tokenRequest->getBody()->write(http_build_query([
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]));

                $response    = $this->httpClient->sendRequest($tokenRequest);
                $body        = json_decode($response->getBody()->getContents(), true);
                $accessToken = $body['access_token'];

                // Cache the new Token
                $cachedToken
                    ->set($accessToken)
                    ->expiresAfter($body['expires_in'] - 10);

                $this->cache->save($cachedToken);
            }

            $this->accessToken = $accessToken;
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage());
            throw new PackagingTypesApiException("Couldn't connect to Packaging Types API.");
        }
    }

    /**
     * Get the list of Packaging Types.
     *
     * @param PackagingTypeSortField $sortBy The field by which to sort the packaging types
     * @param bool $onlyActive If true, return only active packaging types
     * @param bool $refreshCache If true, packaging types are fetched from the API and the cache is refreshed
     * @return array
     * @throws PackagingTypesApiException
     */
    public function getPackagingTypes(
        PackagingTypeSortField $sortBy,
        bool $onlyActive = false,
        bool $refreshCache = false,
    ): array {
        try {
            $this->console?->writeln(sprintf(
                "[%s] Fetching Packaging Types from API...",
                (new \DateTime())->format(\DateTimeInterface::RFC3339)
            ));

            // Get the packaging types from the cache
            $cachedPackagingTypes = $this->cache->getItem(self::CACHE_KEY_PACKAGING_TYPES);

            // If the cached packaging types are still valid and if there is no forced refresh, return them
            if (!$refreshCache && $cachedPackagingTypes->isHit()) {
                $cacheResult = $cachedPackagingTypes->get();

                $this->console?->writeln(sprintf(
                    "[%s] Fetched %s packaging types from cache.",
                    (new \DateTime())->format(\DateTimeInterface::RFC3339),
                    count($cacheResult)
                ));

                return $cacheResult;
            }

            $apiResponse    = $this->fetchPackagingTypesFromApi($sortBy, $onlyActive);
            $packagingTypes = [];

            // Loop over the result from the API and create Packaging Type entities
            foreach ($apiResponse as $packagingTypeData) {
                $packagingType    = $this->hydratePackagingType($packagingTypeData);
                $packagingTypes[] = $packagingType;

                // Also cache each Packaging Type individually
                $this->cachePackagingType($packagingType);
            }

            // Cache the list of packaging types
            $cachedPackagingTypes
                ->set($packagingTypes)
                ->expiresAfter(self::CACHE_TTL);

            $this->cache->save($cachedPackagingTypes);

            $this->console?->writeln(sprintf(
                "[%s] Cached the Packaging Types list, containing %s entries.",
                (new \DateTime())->format(\DateTimeInterface::RFC3339),
                count($packagingTypes)
            ));

            return $packagingTypes;
        } catch (Throwable $e) {
            $this->logger?->error($e->getMessage());
            throw new PackagingTypesApiException("Couldn't retrieve the list of Packaging Types.");
        }
    }

    /**
     * Get a single packaging type.
     *
     * @param string $id The Packaging Type ID
     * @return PackagingTypeEntity|null
     * @throws PackagingTypesApiException
     */
    public function getPackagingType(string $id): ?PackagingTypeEntity
    {
        try {
            // Get the packaging type from the cache
            $cachedPackagingType = $this->cache->getItem(sprintf(self::CACHE_KEY_PACKAGING_TYPE, $id));

            // If the cached packaging type is still valid, return it
            if ($cachedPackagingType->isHit()) {
                return $cachedPackagingType->get();
            }

            // If the cached users are no longer valid, get them from the Users API
            $this->loginToAuthServer();

            $uri = $this->uriFactory
                ->createUri(rtrim($this->apiUrl, '/') . self::PACKAGING_TYPES_URI . '/' . $id);

            $request = $this->requestFactory->createRequest('GET', $uri);
            $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
            $request = $request->withHeader('Accept', 'application/ld+json');

            $response = $this->httpClient->sendRequest($request);

            // Check for errors
            if ($response->getStatusCode() >= 400) {
                if ($response->getStatusCode() === 404) {
                    return null;
                }

                throw new \Exception(
                    sprintf("Received status code %s from Packaging Types API.", $response->getStatusCode())
                );
            }

            $response      = json_decode($response->getBody()->getContents(), true);
            $packagingType = $this->hydratePackagingType($response);

            // Cache the Packaging Type
            $cachedPackagingType
                ->set($packagingType)
                ->expiresAfter(self::CACHE_TTL);

            $this->cache->save($cachedPackagingType);

            return $packagingType;
        } catch (\Throwable | InvalidArgumentException $e) {
            $this->logger?->error($e->getMessage());
            throw new PackagingTypesApiException("Couldn't retrieve the list of Packaging Types.");
        }
    }

    protected function fetchPackagingTypesFromApi(
        PackagingTypeSortField $sortBy,
        bool $onlyActive = false
    ): array {
        $this->loginToAuthServer();

        $queryParams = ['sortBy' => $sortBy->value];

        if ($onlyActive) {
            $queryParams['active'] = 'true';
        }

        $uri = $this->uriFactory
            ->createUri(rtrim($this->apiUrl, '/') . self::PACKAGING_TYPES_URI)
            ->withQuery(http_build_query($queryParams));

        $request = $this->requestFactory->createRequest('GET', $uri);
        $request = $request->withHeader('Authorization', sprintf("Bearer %s", $this->accessToken));
        $request = $request->withHeader('Accept', 'application/ld+json');

        $response = $this->httpClient->sendRequest($request);
        $response = json_decode($response->getBody()->getContents(), true);

        return $response['member'];
    }

    protected function hydratePackagingType(array $packagingTypeData): PackagingTypeEntity
    {
        $packagingType = new PackagingTypeEntity();
        $packagingType
            ->setId(Uuid::fromString($packagingTypeData['id']))
            ->setName($packagingTypeData['name'])
            ->setShortName($packagingTypeData['shortName'])
            ->setTransporeonId($packagingTypeData['transporeonId'])
            ->setCategory(Category::from($packagingTypeData['category']))
            ->setActive($packagingTypeData['active'])
            ->setLength($packagingTypeData['length'])
            ->setWidth($packagingTypeData['width'])
            ->setHeight($packagingTypeData['height'])
            ->setWeight($packagingTypeData['weight'])
            ->setMaxNbStackable($packagingTypeData['maxNbStackable']);

        return $packagingType;
    }

    /**
     * Cache a Packaging Type.
     *
     * @param PackagingTypeEntity $packagingType
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function cachePackagingType(PackagingTypeEntity $packagingType): void
    {
        $cachedPackagingType = $this->cache->getItem(
            sprintf(self::CACHE_KEY_PACKAGING_TYPE, $packagingType->getId()->toString())

        );
        $cachedPackagingType
            ->expiresAfter(self::CACHE_TTL)
            ->set($packagingType);

        $this->cache->save($cachedPackagingType);

        $this->console?->writeln(sprintf(
            "[%s] Cached Packaging Type '%s'.",
            (new \DateTime())->format(\DateTimeInterface::RFC3339),
            $packagingType->getName()
        ));
    }
}
