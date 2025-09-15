<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Controller\Adminhtml\Feature;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Xpify\AppFeature\Model\FeatureFactory;
use Xpify\AppFeature\Model\ResourceModel\Feature as FeatureResource;
use Xpify\Core\Model\Logger;

class Save extends Action
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var FeatureFactory
     */
    private FeatureFactory $featureFactory;

    /**
     * @var FeatureResource
     */
    private FeatureResource $featureResource;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param FeatureFactory $featureFactory
     * @param FeatureResource $featureResource
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FeatureFactory $featureFactory,
        FeatureResource $featureResource
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->featureFactory = $featureFactory;
        $this->featureResource = $featureResource;
    }

    /**
     * Save feature action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        try {
            $data = $this->getRequest()->getParams();
            
            if (empty($data)) {
                throw new LocalizedException(__('Invalid form data.'));
            }

            $featureId = $data['entity_id'] ?? null;
            
            if ($featureId) {
                $feature = $this->featureFactory->create();
                $this->featureResource->load($feature, (int) $featureId);
                if (!$feature->getId()) {
                    throw new LocalizedException(__('Feature not found.'));
                }
            } else {
                $feature = $this->featureFactory->create();
            }

            // Set feature data
            if (isset($data['feature_key'])) {
                $feature->setFeatureKey($data['feature_key']);
            }
            if (isset($data['feature_name'])) {
                $feature->setFeatureName($data['feature_name']);
            }
            if (isset($data['description'])) {
                $feature->setDescription($data['description']);
            }
            if (isset($data['required_version'])) {
                $feature->setRequiredVersion($data['required_version'] ?: null);
            }
            if (isset($data['is_enabled_by_default'])) {
                $feature->setIsEnabledByDefault((bool) $data['is_enabled_by_default']);
            }

            $this->featureResource->save($feature);
            
            Logger::getLogger('feature_admin.log')->debug('Feature saved', [
                'feature_id' => $feature->getId(),
                'feature_key' => $feature->getFeatureKey()
            ]);

            return $resultJson->setData([
                'success' => true,
                'message' => __('Feature saved successfully.'),
                'feature_id' => $feature->getId()
            ]);

        } catch (\Throwable $e) {
            Logger::getLogger('feature_admin.log')->error('Error saving feature', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $resultJson->setData([
                'success' => false,
                'message' => __('Error saving feature: %1', $e->getMessage())
            ]);
        }
    }

    /**
     * Check if admin has permission to access this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Xpify_App::manage');
    }
}