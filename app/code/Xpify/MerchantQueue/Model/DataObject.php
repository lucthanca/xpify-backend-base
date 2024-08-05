<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model;
use Magento\Framework\DataObject as MDataObject;

final class DataObject
{
    /**
     * @param MDataObject $object
     * @param $fields
     * @return array|mixed|null
     */
    public static function getData(MDataObject $object, $fields = null): mixed
    {
        if ($fields === null) {
            return $object->getData();
        }
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $object->getData($field);
        }
        return $data;
    }
}
