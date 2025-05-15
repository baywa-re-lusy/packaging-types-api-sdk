<?php

namespace BayWaReLusy\PackagingTypesAPI\Test\Console;

use BayWaReLusy\PackagingTypesAPI\SDK\Console\RefreshPackagingTypesCache;
use BayWaReLusy\PackagingTypesAPI\SDK\PackagingTypesApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshPackagingTypesCacheTest extends TestCase
{
    protected RefreshPackagingTypesCache $instance;
    protected MockObject $packagingTypesApiClientMock;
    protected MockObject $inputInterfaceMock;
    protected MockObject $outputInterfaceMock;

    protected function setUp(): void
    {
        $this->packagingTypesApiClientMock = $this->createMock(PackagingTypesApiClient::class);
        $this->inputInterfaceMock          = $this->createMock(InputInterface::class);
        $this->outputInterfaceMock         = $this->createMock(OutputInterface::class);

        $this->instance = new RefreshPackagingTypesCache($this->packagingTypesApiClientMock);
    }

    public function testExecute(): void
    {
        // The execute method is protected. Make it available through reflection
        $reflectionClass = new \ReflectionClass(RefreshPackagingTypesCache::class);
        $executeMethod   = $reflectionClass->getMethod('execute');
        $executeMethod->setAccessible(true);

        // Expect the setConsole() call
        $this->packagingTypesApiClientMock
            ->expects($this->once())
            ->method('setConsole')
            ->with($this->outputInterfaceMock)
            ->willReturnSelf();

        // Expect the getUsers() call
        $this->packagingTypesApiClientMock
            ->expects($this->once())
            ->method('getPackagingTypes');

        $result = $executeMethod->invoke($this->instance, $this->inputInterfaceMock, $this->outputInterfaceMock);

        $this->assertEquals(Command::SUCCESS, $result);
    }
}
