<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl\Config\Element;

use Magento\Framework\GraphQl\Config\Element\ArgumentFactory;
use Magento\Framework\GraphQl\Config\Element\FieldFactory;
use Xpify\Core\GraphQl\Filter as FieldFilter;

class FieldsFactory extends \Magento\Framework\GraphQl\Config\Element\FieldsFactory
{
    /**
     * @var ArgumentFactory
     */
    private $argumentFactory;

    /**
     * @var FieldFactory
     */
    private $fieldFactory;

    /**
     * @var FieldFilter
     */
    private $fieldFilter;

    /**
     * @param ArgumentFactory $argumentFactory
     * @param FieldFactory $fieldFactory
     * @param FieldFilter $fieldFilter
     */
    public function __construct(
        ArgumentFactory $argumentFactory,
        FieldFactory $fieldFactory,
        FieldFilter $fieldFilter
    ) {
        $this->argumentFactory = $argumentFactory;
        $this->fieldFactory = $fieldFactory;
        $this->fieldFilter = $fieldFilter;
        parent::__construct($argumentFactory, $fieldFactory);
    }
    public function createFromConfigData(
        array $fieldsData
    ) : array {
        $fields = [];
        foreach ($fieldsData as $fieldData) {
            $arguments = [];
            if (isset($fieldData['app'])) {
                $isAllowed = $this->fieldFilter->isAllowed($fieldData['app']);
                if (!$isAllowed) {
                    continue;
                }
            }

            foreach ($fieldData['arguments'] as $argumentData) {
                $arguments[$argumentData['name']] = $this->argumentFactory->createFromConfigData($argumentData);
            }
            $fields[$fieldData['name']] = $this->fieldFactory->createFromConfigData(
                $fieldData,
                $arguments
            );
        }
        return $fields;
    }
}
