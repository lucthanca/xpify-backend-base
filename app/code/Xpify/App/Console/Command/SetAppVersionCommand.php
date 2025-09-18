<?php
declare(strict_types=1);

namespace Xpify\App\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\App\Service\AppVersionManager;
use Xpify\App\Service\GetCurrentApp;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\App\Api\Data\AppMetadataInterface as IAppMetadata;
use Xpify\App\Model\ResourceModel\AppMetadata as AppMetadataResource;

class SetAppVersionCommand extends Command
{
    const ARGUMENT_VERSION = 'version';
    const ARGUMENT_APP_ID = 'app_id';

    private FeatureManager $featureManager;
    private AppRepositoryInterface $appRepository;
    private AppMetadataResource $resource;
    private \Xpify\App\Service\AppVersionManager $appVersionManager;
    private \Xpify\App\Service\GetCurrentApp $app;

    /**
     * @param AppRepositoryInterface $appRepository
     * @param AppMetadataResource $resource
     * @param AppVersionManager $appVersionManager
     * @param GetCurrentApp $app
     * @param string|null $name
     */
    public function __construct(
        AppRepositoryInterface $appRepository,
        AppMetadataResource $resource,
        \Xpify\App\Service\AppVersionManager $appVersionManager,
        \Xpify\App\Service\GetCurrentApp $app,
        string $name = null
    ) {
        parent::__construct($name);
        $this->appRepository = $appRepository;
        $this->resource = $resource;
        $this->appVersionManager = $appVersionManager;
        $this->app = $app;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('xpify:app:version:set')
            ->setDescription('Set the current app version for feature gating')
            ->addArgument(
                self::ARGUMENT_VERSION,
                InputArgument::REQUIRED,
                'The new app version (e.g., 1.2.0)'
            )->addArgument(
                self::ARGUMENT_APP_ID,
                InputArgument::REQUIRED,
                'The App ID to update'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $newVersion = $input->getArgument(self::ARGUMENT_VERSION);
            $appId = (int)$input->getArgument(self::ARGUMENT_APP_ID);

            $app = $this->appRepository->get($appId);
            if (!$app?->getId()) {
                $output->writeln("<error>No app found with ID {$appId}</error>");
                return Cli::RETURN_FAILURE;
            }
            $this->app->set($app)->lock();

            $currentVersion = $this->appVersionManager->getCurrentAppVersion();
            $output->writeln("<info>Current app version: {$currentVersion}</info>");

            $this->appVersionManager->setCurrentAppVersion($newVersion);
            $output->writeln("<info>App version updated to: {$newVersion}</info>");
            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}
