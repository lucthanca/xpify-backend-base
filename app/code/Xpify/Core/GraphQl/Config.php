<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

use Magento\Framework\Config\DataInterface;
use Magento\Framework\GraphQl\Config\ConfigElementFactoryInterface;
use Magento\Framework\GraphQl\Query\Fields as QueryFields;
use Xpify\Core\GraphQl\Filter as FieldFilter;

class Config extends \Magento\Framework\GraphQl\Config
{
    /**
     * @var DataInterface
     */
    private $configData;

    /**
     * @var ConfigElementFactoryInterface
     */
    private $configElementFactory;

    /**
     * @var QueryFields
     */
    private $queryFields;
    private FieldFilter $fieldFilter;

    /**
     * @param DataInterface $data
     * @param ConfigElementFactoryInterface $configElementFactory
     * @param QueryFields $queryFields
     * @param FieldFilter $fieldFilter
     */
    public function __construct(
        DataInterface $data,
        ConfigElementFactoryInterface $configElementFactory,
        QueryFields $queryFields,
        FieldFilter $fieldFilter
    ) {
        $this->configData = $data;
        $this->configElementFactory = $configElementFactory;
        $this->queryFields = $queryFields;
        $this->fieldFilter = $fieldFilter;
        parent::__construct($data, $configElementFactory, $queryFields);
    }
    /**
     * @inheritdoc
     */
    public function getDeclaredTypes() : array
    {
        $types = [];
        foreach ($this->configData->get(null) as $item) {
            if (isset($item['type'])) {
                if (isset($item['app'])) {
                    $isAllowed = $this->fieldFilter->isAllowed($item['app']);
                    if (!$isAllowed) {
                        continue;
                    }
                }
                $types[] = [
                    'name' => $item['name'],
                    'type' => $item['type'],
                ];
            }
        }

        return $types;
    }
}
