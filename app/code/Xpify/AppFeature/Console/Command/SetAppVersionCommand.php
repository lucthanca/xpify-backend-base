<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\Core\Model\Logger;

class SetAppVersionCommand extends Command
{
    const ARGUMENT_VERSION = 'version';
    
    private FeatureManager $featureManager;

    /**
     * @param FeatureManager $featureManager
     * @param string|null $name
     */
    public function __construct(
        FeatureManager $featureManager,
        string $name = null
    ) {
        $this->featureManager = $featureManager;
        parent::__construct($name);
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
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $newVersion = $input->getArgument(self::ARGUMENT_VERSION);
            
            // Validate version format (basic semver check)
            if (!$this->isValidVersion($newVersion)) {
                $output->writeln('<error>Invalid version format. Please use semantic versioning (e.g., 1.2.0)</error>');
                return Cli::RETURN_FAILURE;
            }

            $currentVersion = $this->featureManager->getCurrentAppVersion();
            $output->writeln("<info>Current app version: {$currentVersion}</info>");

            if ($this->featureManager->setCurrentAppVersion($newVersion)) {
                $output->writeln("<info>Successfully updated app version to: {$newVersion}</info>");
                
                // Log the version change
                Logger::getLogger('app_version.log')->info(
                    "App version updated from {$currentVersion} to {$newVersion} via CLI"
                );
                
                return Cli::RETURN_SUCCESS;
            } else {
                $output->writeln('<error>Failed to update app version. Check logs for details.</error>');
                return Cli::RETURN_FAILURE;
            }

        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            Logger::getLogger('app_version.log')->error(
                "CLI error setting app version: " . $e->getMessage(),
                ['exception' => $e]
            );
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
        return preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9\-]+)?(\+[a-zA-Z0-9\-]+)?$/', $version);
    }
}