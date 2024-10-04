<?php
declare(strict_types=1);

namespace Xpify\Merchant\Ui\Component\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;
use Xpify\Merchant\Model\ResourceModel\Merchant\CollectionFactory as FMerchantCollection;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MerchantDataProvider extends ModifierPoolDataProvider
{
    const GENERAL_FIELDSET_NAME = "general";
    const EXTRA_FIELDSET_NAME = 'extra_app_config';
    const DATA_PERSISTOR_KEY = 'xpify_merchant';

    protected $loadedData;

    private DataPersistorInterface $dataPersistor;
    private RequestInterface $request;
    private FMerchantCollection $collectionFactory;

    /**
     * @param FMerchantCollection $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param RequestInterface $request
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        FMerchantCollection $collectionFactory,
        DataPersistorInterface $dataPersistor,
        RequestInterface $request,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = [],
        PoolInterface $pool = null
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
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
        $data = $this->dataPersistor->get(static::DATA_PERSISTOR_KEY);
        if (!empty($data)) {
            $items = $this->collectionFactory->create();
            if (!empty($data['entity_id'])) {
                $items->addFieldToFilter(IMerchant::ID, $data['entity_id']);
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
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
        }
        // return loaded data
        return $this->loadedData;
    }
}
