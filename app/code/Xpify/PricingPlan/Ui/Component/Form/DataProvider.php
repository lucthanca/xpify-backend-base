<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Ui\Component\Form;

use Magento\Directory\Model\Currency;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Model\ResourceModel\PricingPlan\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{

    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @var ContextInterface
     */
    private ContextInterface $context;
    private \Magento\Directory\Model\Currency $currency;

    /**
     * DataProvider constructor.
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param ContextInterface $context
     * @param Currency $currency
     * @param array $meta
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        ContextInterface $context,
        Currency $currency,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->context    = $context;
        $this->currency = $currency;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get roles data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        /* @var IPricingPlan $item */
        foreach ($items as $item) {
            $this->prepareItemData($item);
            $this->loadedData[$item->getId()] = $this->prepareItemData($item);
        }
        $this->loadedData[''] = $this->getDefaultData();

        $this->populateCurrencyData();
        return $this->loadedData;
    }

    /**
     * Prepare item data
     *
     * @param IPricingPlan $item
     *
     * @return array
     */
    public function prepareItemData(IPricingPlan $item): array
    {
        return array_merge(
            $item->getData(),
            [
                IPricingPlan::PRICES => $item->getDataPrices(),
                IPricingPlan::BADGES => $item->getBadges(),
            ]
        );
    }

    /**
     * Populate currency symbol
     *
     * In general, this is not a best practice. Should be done in a meta modifier, but for some reason, the meta is empty at all.
     * So we do it here. and import to field through ui_component xml.
     *
     * @see getMeta()
     */
    private function populateCurrencyData(): void
    {
        $currency = $this->currency->load(IPricingPlan::BASE_CURRENCY);
        $currency->getCurrencySymbol();
        foreach ($this->loadedData as &$dItem) {
            $dItem['currency'] = $currency->getCurrencySymbol();
        }
    }

    /**
     * Get default customer data for adding new role
     *
     * @return array
     */
    private function getDefaultData(): array
    {
        return [
            'app_id' => $this->context->getRequestParam('app_id'),
        ];
    }
}
