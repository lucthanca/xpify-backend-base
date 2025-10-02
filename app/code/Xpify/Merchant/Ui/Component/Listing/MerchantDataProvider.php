<?php
declare(strict_types=1);

namespace Xpify\Merchant\Ui\Component\Listing;

use Xpify\Merchant\Model\ResourceModel\Merchant\CollectionFactory as MerchantCollectionFactory;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Magento\Framework\Api\Filter;
use Xpify\App\Api\Data\AppInterface as IApp;

class MerchantDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    private \Xpify\App\Model\ResourceModel\App $resourceApp;

    public function __construct(
        MerchantCollectionFactory $factory,
        \Xpify\App\Model\ResourceModel\App $resourceApp,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $factory->create();
        $this->resourceApp = $resourceApp;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $collection = $this->getCollection();
        $data = $collection->toArray();
        $items = &$data['items'];
        foreach ($items as &$item) {
            $this->modifyItemData($item);
        }
        return $data;
    }

    /**
     * Modify item data, e.g. add online_access_info
     *
     * @param array $i - Item Data
     */
    public function modifyItemData(array &$i): void
    {
        $onlineAccessInfoFields = [
            'user_id', 'user_first_name', 'user_last_name', 'user_email', 'user_email_verified', 'account_owner', 'locale', 'collaborator'
        ];
        $onlineAccessInfo = [];
        foreach ($onlineAccessInfoFields as $f) {
            $onlineAccessInfo[$f] = $i[$f];
            unset($i[$f]);
        }
        $i['online_access_info'] = $onlineAccessInfo;
        if (empty($i['cohort_version'])) {
            $i['cohort_version'] = IMerchant::COHORT_VERSION_DEFAULT;
        }
    }

    public function addFilter(Filter $filter)
    {
        if ($filter->getField() !== 'fulltext') {
            $this->getCollection()->addFieldToFilter(
                $filter->getField(),
                [$filter->getConditionType() => $filter->getValue()]
            );
        } else {
            $value = trim($filter->getValue());
            // define need fulltext fields
            $fulltextFields = [
                IMerchant::SESSION_ID,
                IMerchant::SHOP,
                IMerchant::SCOPE,
                IMerchant::ACCESS_TOKEN,
                IMerchant::STOREFRONT_ACCESS_TOKEN,
                IMerchant::USER_FIRST_NAME,
                IMerchant::USER_LAST_NAME,
                IMerchant::USER_EMAIL,
                IMerchant::LOCALE,
            ];
            // get all app ids by fulltext search term
            $appConnection = $this->resourceApp->getConnection();
            $select = $appConnection->select();
            $select->from($this->resourceApp->getMainTable(), [IApp::ID]);
            $select->where(IApp::NAME . ' LIKE ?', "%$value%");
            $appIds = $appConnection->fetchCol($select);

            // make filter
            $filterFields = array_map(function ($f) {
                return ['attribute' => $f];
            }, $fulltextFields);
            $filterFieldVales = array_fill(0, count($fulltextFields), ['like' => "%$value%"]);
            $filterFields[] = ['attribute' => IMerchant::APP_ID];
            $filterFieldVales[] = ['in' => implode(',', $appIds)];
            $this->collection->addFieldToFilter(
                $filterFields,
                $filterFieldVales
            );
        }
    }
}
