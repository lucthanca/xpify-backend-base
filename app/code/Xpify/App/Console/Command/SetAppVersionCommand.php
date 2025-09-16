<?php
declare(strict_types=1);

namespace Xpify\App\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\App\Api\Data\AppMetadataInterface as IAppMetadata;

class SetAppVersionCommand extends Command
{
    const ARGUMENT_VERSION = 'version';
    const ARGUMENT_APP_ID = 'app_id';

    private FeatureManager $featureManager;
    private AppRepositoryInterface $appRepository;

    /**
     * @param AppRepositoryInterface $appRepository
     * @param string|null $name
     */
    public function __construct(
        AppRepositoryInterface $appRepository,
        string $name = null
    ) {
        parent::__construct($name);
        $this->appRepository = $appRepository;
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

            // Validate version format (basic semver check)
            if (!$this->isValidVersion($newVersion)) {
                $output->writeln('<error>Invalid version format. Please use semantic versioning (e.g., 1.2.0)</error>');
                return Cli::RETURN_FAILURE;
            }

            $app = $this->appRepository->get($appId);
            if (!$app?->getId()) {
                $output->writeln("<error>No app found with ID {$appId}</error>");
                return Cli::RETURN_FAILURE;
            }
            $appMetadata = $app->getMetadata();
            if (!$appMetadata) {
                $output->writeln("<error>Technical error</error>");
                return Cli::RETURN_FAILURE;
            }
            /** @var IAppMetadata $metadata */
            $metadata = $appMetadata->getData('app_version') ?? null;
            if (!$metadata) {
                $metadata = \Magento\Framework\App\ObjectManager::getInstance()->create(IAppMetadata::class);
                $metadata->setMetadataKey('app_version');
                $metadata->setId($appId);
            }
            $currentVersion = $metadata?->getMetadataValue()['current'] ?? 'N/A';
            $output->writeln("<info>Current app version: {$currentVersion}</info>");

            $metadata->setMetadataValue(['current' => $newVersion])->save();
            $output->writeln("<info>App version updated to: {$newVersion}</info>");
            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
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
