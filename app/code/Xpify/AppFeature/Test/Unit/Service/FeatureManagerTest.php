<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Test\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Xpify\AppFeature\Model\ResourceModel\AppMetadata as AppMetadataResource;
use Xpify\AppFeature\Model\ResourceModel\Feature\Collection as FeatureCollection;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory as FeatureCollectionFactory;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature\Collection as MerchantFeatureCollection;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature\CollectionFactory as MerchantFeatureCollectionFactory;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\Merchant\Api\Data\MerchantInterface;
use Xpify\Merchant\Model\MerchantFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class FeatureManagerTest extends TestCase
{
    /** @var FeatureManager */
    private $featureManager;

    /** @var MockObject|FeatureCollectionFactory */
    private $featureCollectionFactoryMock;

    /** @var MockObject|MerchantFeatureCollectionFactory */
    private $merchantFeatureCollectionFactoryMock;

    /** @var MockObject|AppMetadataResource */
    private $appMetadataResourceMock;

    /** @var MockObject|MerchantFactory */
    private $merchantFactoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureCollectionFactoryMock = $this->createMock(FeatureCollectionFactory::class);
        $this->merchantFeatureCollectionFactoryMock = $this->createMock(MerchantFeatureCollectionFactory::class);
        $this->appMetadataResourceMock = $this->createMock(AppMetadataResource::class);
        $this->merchantFactoryMock = $this->createMock(MerchantFactory::class);

        $this->featureManager = new FeatureManager(
            $this->featureCollectionFactoryMock,
            $this->merchantFeatureCollectionFactoryMock,
            $this->appMetadataResourceMock,
            $this->merchantFactoryMock
        );
    }

    /**
     * Test feature is enabled when merchant has explicit override set to enabled
     */
    public function testIsFeatureEnabledWithMerchantOverrideEnabled(): void
    {
        $merchantId = 123;
        $featureKey = 'advanced_analytics';

        // Mock merchant feature collection to return enabled override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('merchant_id', $merchantId)
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createMerchantFeatureMock($merchantId, $featureKey, true));

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertTrue($result);
    }

    /**
     * Test feature is disabled when merchant has explicit override set to disabled
     */
    public function testIsFeatureEnabledWithMerchantOverrideDisabled(): void
    {
        $merchantId = 123;
        $featureKey = 'advanced_analytics';

        // Mock merchant feature collection to return disabled override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('merchant_id', $merchantId)
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createMerchantFeatureMock($merchantId, $featureKey, false));

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertFalse($result);
    }

    /**
     * Test feature is enabled when no override exists and merchant cohort version meets requirement
     */
    public function testIsFeatureEnabledByCohortVersion(): void
    {
        $merchantId = 123;
        $featureKey = 'advanced_analytics';

        // Mock no merchant override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createEmptyMerchantFeatureMock());

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        // Mock merchant with cohort version 1.2.0
        $merchantMock = $this->createMock(MerchantInterface::class);
        $merchantMock->expects($this->once())
            ->method('getCohortVersion')
            ->willReturn('1.2.0');

        $this->merchantFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantMock);

        // Mock feature with required version 1.1.0
        $featureCollectionMock = $this->createMock(FeatureCollection::class);
        $featureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $featureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createFeatureMock($featureKey, '1.1.0', false));

        $this->featureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($featureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertTrue($result);
    }

    /**
     * Test feature is disabled when merchant cohort version doesn't meet requirement
     */
    public function testIsFeatureDisabledByCohortVersion(): void
    {
        $merchantId = 123;
        $featureKey = 'advanced_analytics';

        // Mock no merchant override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createEmptyMerchantFeatureMock());

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        // Mock merchant with cohort version 1.0.0
        $merchantMock = $this->createMock(MerchantInterface::class);
        $merchantMock->expects($this->once())
            ->method('getCohortVersion')
            ->willReturn('1.0.0');

        $this->merchantFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantMock);

        // Mock feature with required version 1.2.0
        $featureCollectionMock = $this->createMock(FeatureCollection::class);
        $featureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $featureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createFeatureMock($featureKey, '1.2.0', false));

        $this->featureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($featureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertFalse($result);
    }

    /**
     * Test feature uses default enabled state when no override and no version requirement
     */
    public function testIsFeatureEnabledByDefault(): void
    {
        $merchantId = 123;
        $featureKey = 'basic_feature';

        // Mock no merchant override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createEmptyMerchantFeatureMock());

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        // Mock merchant (cohort version won't be checked)
        $merchantMock = $this->createMock(MerchantInterface::class);
        $this->merchantFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantMock);

        // Mock feature with no version requirement and default enabled
        $featureCollectionMock = $this->createMock(FeatureCollection::class);
        $featureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $featureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createFeatureMock($featureKey, null, true));

        $this->featureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($featureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertTrue($result);
    }

    /**
     * Test feature returns false when feature doesn't exist
     */
    public function testIsFeatureEnabledWithNonExistentFeature(): void
    {
        $merchantId = 123;
        $featureKey = 'non_existent_feature';

        // Mock no merchant override
        $merchantFeatureCollectionMock = $this->createMock(MerchantFeatureCollection::class);
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->willReturnSelf();
        $merchantFeatureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createEmptyMerchantFeatureMock());

        $this->merchantFeatureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantFeatureCollectionMock);

        // Mock merchant
        $merchantMock = $this->createMock(MerchantInterface::class);
        $this->merchantFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($merchantMock);

        // Mock feature collection returning no feature
        $featureCollectionMock = $this->createMock(FeatureCollection::class);
        $featureCollectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with('feature_key', $featureKey)
            ->willReturnSelf();
        $featureCollectionMock->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($this->createEmptyFeatureMock());

        $this->featureCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($featureCollectionMock);

        $result = $this->featureManager->isFeatureEnabled($merchantId, $featureKey);
        $this->assertFalse($result);
    }

    /**
     * Test getCurrentAppVersion
     */
    public function testGetCurrentAppVersion(): void
    {
        $expectedVersion = '1.2.3';
        
        $this->appMetadataResourceMock->expects($this->once())
            ->method('getMetadataValue')
            ->with('app_version')
            ->willReturn($expectedVersion);

        $result = $this->featureManager->getCurrentAppVersion();
        $this->assertEquals($expectedVersion, $result);
    }

    /**
     * Test setCurrentAppVersion
     */
    public function testSetCurrentAppVersion(): void
    {
        $newVersion = '2.0.0';
        
        $this->appMetadataResourceMock->expects($this->once())
            ->method('setMetadataValue')
            ->with('app_version', $newVersion)
            ->willReturn(true);

        $result = $this->featureManager->setCurrentAppVersion($newVersion);
        $this->assertTrue($result);
    }

    /**
     * Create a mock MerchantFeature with data
     */
    private function createMerchantFeatureMock(int $merchantId, string $featureKey, bool $isEnabled): MockObject
    {
        $mock = $this->getMockBuilder(\Xpify\AppFeature\Model\MerchantFeature::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->method('getId')->willReturn(1);
        $mock->method('getMerchantId')->willReturn($merchantId);
        $mock->method('getFeatureKey')->willReturn($featureKey);
        $mock->method('getIsEnabled')->willReturn($isEnabled);
        
        return $mock;
    }

    /**
     * Create an empty MerchantFeature mock
     */
    private function createEmptyMerchantFeatureMock(): MockObject
    {
        $mock = $this->getMockBuilder(\Xpify\AppFeature\Model\MerchantFeature::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->method('getId')->willReturn(null);
        
        return $mock;
    }

    /**
     * Create a mock Feature with data
     */
    private function createFeatureMock(string $featureKey, ?string $requiredVersion, bool $defaultEnabled): MockObject
    {
        $mock = $this->getMockBuilder(\Xpify\AppFeature\Model\Feature::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->method('getId')->willReturn(1);
        $mock->method('getFeatureKey')->willReturn($featureKey);
        $mock->method('getRequiredVersion')->willReturn($requiredVersion);
        $mock->method('getIsEnabledByDefault')->willReturn($defaultEnabled);
        
        return $mock;
    }

    /**
     * Create an empty Feature mock
     */
    private function createEmptyFeatureMock(): MockObject
    {
        $mock = $this->getMockBuilder(\Xpify\AppFeature\Model\Feature::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->method('getId')->willReturn(null);
        
        return $mock;
    }
}