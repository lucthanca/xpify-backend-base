<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Ui\Component\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Xpify\AppFeature\Model\ResourceModel\Feature\Collection;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory;

class FeatureDataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        $this->loadedData = [];
        
        /** @var \Xpify\AppFeature\Model\Feature $feature */
        foreach ($items as $feature) {
            $this->loadedData[$feature->getId()] = $feature->getData();
        }
        
        // Check if data is persisted from form submission
        $data = $this->dataPersistor->get('xpify_appfeature_feature');
        if (!empty($data)) {
            $feature = $this->collection->getNewEmptyItem();
            $feature->setData($data);
            $this->loadedData[$feature->getId()] = $feature->getData();
            $this->dataPersistor->clear('xpify_appfeature_feature');
        }
        
        return $this->loadedData;
    }
}