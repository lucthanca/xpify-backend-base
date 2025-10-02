<?php
declare(strict_types=1);

namespace Xpify\App\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\App\Api\Data\AppMetadataInterface as IAppMetadata;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class AppVersionManager
{
    private GetCurrentApp $app;

    /**
     * @param GetCurrentApp $app
     */
    public function __construct(
        GetCurrentApp $app
    ) {
        $this->app = $app;
    }

    /**
     * Get app metadata for versioning
     *
     * @return IAppMetadata|null
     * @throws NoSuchEntityException
     */
    private function getVersionAppMetadata(): ?IAppMetadata
    {
        $app = $this->app->get();
        if (!$app?->getId()) {
            throw new NoSuchEntityException(__("No app set!"));
        }
        $appMetadata = $app->getMetadata();
        if (!$appMetadata) {
            throw new NoSuchEntityException(__("No app metadata found!"));
        }
        /** @var IAppMetadata $metadata */
        return $appMetadata->getData('app_version') ?? null;
    }

    /**
     * Get current app version from app metadata
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCurrentAppVersion(): string
    {
        try {
            $metadata = $this->getVersionAppMetadata();
            if (!$metadata) {
                return IMerchant::COHORT_VERSION_DEFAULT;
            }
            return $metadata->getMetadataValue()['current'] ?? IMerchant::COHORT_VERSION_DEFAULT;
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Logger::getLogger('app.version_manager.log')->debug($e->getMessage(), $e->getTrace());
            throw new NoSuchEntityException(__("No app version found!"));
        }
    }

    /**
     * Set current app version in app metadata
     *
     * @param string $version
     * @return void
     * @throws \RuntimeException|\InvalidArgumentException
     */
    public function setCurrentAppVersion(string $version): void
    {
        $this->isValidVersion($version) ?: throw new \InvalidArgumentException("Invalid version format. Please use semantic versioning (e.g., 1.2.0)");
        try {
            $metadata = $this->getVersionAppMetadata();
            if (!$metadata) {
                $metadata = \Magento\Framework\App\ObjectManager::getInstance()->create(IAppMetadata::class);
                $app = $this->app->get();
                $metadata->setAppId((int)$app->getId());
                $metadata->setMetadataKey('app_version');
            }
            $metadata->setMetadataValue(['current' => $version])->save();
        } catch (NoSuchEntityException $e) {
            throw new \RuntimeException($e->getMessage());
        } catch (\Throwable $e) {
            Logger::getLogger('app.version_manager.log')->debug($e->getMessage(), $e->getTrace());
            throw new \RuntimeException("Failed to set app version!");
        }
    }

    /**
     * Basic semantic version validation
     *
     * @param string $version
     * @return bool
     */
    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9\-]+)?(\+[a-zA-Z0-9\-]+)?$/', $version);
    }
}
