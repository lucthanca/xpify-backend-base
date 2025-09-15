<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Block\Adminhtml\Feature\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        $data = [];
        if ($this->getFeatureId()) {
            $data = [
                'label' => __('Delete Feature'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to delete this feature?'
                ) . '\', \'' . $this->getDeleteUrl() . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }

    /**
     * Get URL for delete button
     *
     * @return string
     */
    public function getDeleteUrl()
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getFeatureId()]);
    }

    /**
     * Return feature ID
     *
     * @return int|null
     */
    public function getFeatureId()
    {
        // This would normally be injected via context/request
        return null; // For modal forms, delete is usually handled differently
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        // In practice, this would use UrlInterface
        return '#';
    }
}