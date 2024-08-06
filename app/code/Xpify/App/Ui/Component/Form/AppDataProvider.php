<?php
declare(strict_types=1);

namespace Xpify\App\Ui\Component\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Xpify\App\Model\ResourceModel\App\CollectionFactory as FAppCollection;
use Xpify\App\Api\Data\AppInterface as IApp;

class AppDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    const GENERAL_FIELDSET_NAME = "general";
    const OTHER_CONFIGURATION_FIELDSET_NAME = 'other_configuration';

    protected $loadedData;
    private $dataPersistor;
    private $request;
    private $collectionFactory;

    /**
     * @param FAppCollection $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param RequestInterface $request
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        FAppCollection $collectionFactory,
        DataPersistorInterface $dataPersistor,
        RequestInterface $request,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        // set collection
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        // if loaded data is set, return loaded data
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        $this->loadedData = [];
        if (!empty($items)) {
            foreach ($items as $item) {
                $data = $item->getData();
                $this->loadedData[$item->getId()][static::GENERAL_FIELDSET_NAME] = $data;
            }
        }
        $data = $this->dataPersistor->get('xpify_app');
        if (!empty($data)) {
            $items = $this->collectionFactory->create();
            if (!empty($data['entity_id'])) {
                $items->addFieldToFilter(IApp::ID, $data['entity_id']);
            }
            if ($items->getSize() === 0) {
                $this->loadedData[$data['entity_id'] ?? ""][static::GENERAL_FIELDSET_NAME] = $data;
            } else {
                foreach ($items as $item) {
                    if ($data['entity_id'] === $item->getId()) {
                        $key = $this->request->getParam('id') ?? "";
                        $this->loadedData[$key][static::GENERAL_FIELDSET_NAME] = $data;
                    }
                }
            }
            // unset data persistor
            $this->dataPersistor->clear('xpify_app');
        }
        // return loaded data
        return $this->loadedData;
    }
}
