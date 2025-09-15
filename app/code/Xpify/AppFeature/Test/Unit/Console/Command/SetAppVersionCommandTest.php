<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Test\Unit\Console\Command;

use Magento\Framework\Console\Cli;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xpify\AppFeature\Console\Command\SetAppVersionCommand;
use Xpify\AppFeature\Service\FeatureManager;

class SetAppVersionCommandTest extends TestCase
{
    /** @var SetAppVersionCommand */
    private $command;

    /** @var MockObject|FeatureManager */
    private $featureManagerMock;

    /** @var MockObject|InputInterface */
    private $inputMock;

    /** @var MockObject|OutputInterface */
    private $outputMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureManagerMock = $this->createMock(FeatureManager::class);
        $this->inputMock = $this->createMock(InputInterface::class);
        $this->outputMock = $this->createMock(OutputInterface::class);

        $this->command = new SetAppVersionCommand($this->featureManagerMock);
    }

    /**
     * Test successful version update
     */
    public function testExecuteSuccessfulUpdate(): void
    {
        $newVersion = '1.2.0';
        $currentVersion = '1.1.0';

        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($newVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('getCurrentAppVersion')
            ->willReturn($currentVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('setCurrentAppVersion')
            ->with($newVersion)
            ->willReturn(true);

        $this->outputMock->expects($this->exactly(2))
            ->method('writeln')
            ->withConsecutive(
                ["<info>Current app version: {$currentVersion}</info>"],
                ["<info>Successfully updated app version to: {$newVersion}</info>"]
            );

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
    }

    /**
     * Test invalid version format
     */
    public function testExecuteInvalidVersion(): void
    {
        $invalidVersion = 'invalid-version';

        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($invalidVersion);

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with('<error>Invalid version format. Please use semantic versioning (e.g., 1.2.0)</error>');

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_FAILURE, $result);
    }

    /**
     * Test failed version update
     */
    public function testExecuteFailedUpdate(): void
    {
        $newVersion = '1.2.0';
        $currentVersion = '1.1.0';

        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($newVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('getCurrentAppVersion')
            ->willReturn($currentVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('setCurrentAppVersion')
            ->with($newVersion)
            ->willReturn(false);

        $this->outputMock->expects($this->exactly(2))
            ->method('writeln')
            ->withConsecutive(
                ["<info>Current app version: {$currentVersion}</info>"],
                ['<error>Failed to update app version. Check logs for details.</error>']
            );

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_FAILURE, $result);
    }

    /**
     * Test exception handling
     */
    public function testExecuteWithException(): void
    {
        $newVersion = '1.2.0';
        $exceptionMessage = 'Database connection failed';

        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($newVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('getCurrentAppVersion')
            ->willThrowException(new \Exception($exceptionMessage));

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with("<error>Error: {$exceptionMessage}</error>");

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_FAILURE, $result);
    }

    /**
     * Test valid semantic versions
     *
     * @dataProvider validVersionProvider
     */
    public function testValidVersionFormats(string $version): void
    {
        $currentVersion = '1.0.0';

        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($version);

        $this->featureManagerMock->expects($this->once())
            ->method('getCurrentAppVersion')
            ->willReturn($currentVersion);

        $this->featureManagerMock->expects($this->once())
            ->method('setCurrentAppVersion')
            ->with($version)
            ->willReturn(true);

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
    }

    /**
     * Test invalid semantic versions
     *
     * @dataProvider invalidVersionProvider
     */
    public function testInvalidVersionFormats(string $version): void
    {
        $this->inputMock->expects($this->once())
            ->method('getArgument')
            ->with('version')
            ->willReturn($version);

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with('<error>Invalid version format. Please use semantic versioning (e.g., 1.2.0)</error>');

        $result = $this->command->run($this->inputMock, $this->outputMock);
        $this->assertEquals(Cli::RETURN_FAILURE, $result);
    }

    /**
     * Valid version formats data provider
     */
    public function validVersionProvider(): array
    {
        return [
            ['1.0.0'],
            ['1.2.3'],
            ['10.20.30'],
            ['1.0.0-alpha'],
            ['1.0.0-beta.1'],
            ['1.0.0+20230101'],
            ['1.0.0-alpha+beta'],
        ];
    }

    /**
     * Invalid version formats data provider
     */
    public function invalidVersionProvider(): array
    {
        return [
            ['1.0'],
            ['1'],
            ['v1.0.0'],
            ['1.0.0.0'],
            ['invalid'],
            [''],
            ['1.0.0-'],
            ['1.0.0+'],
        ];
    }
}