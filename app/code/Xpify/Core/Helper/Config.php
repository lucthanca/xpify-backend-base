<?php
declare(strict_types=1);

namespace Xpify\Core\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface as IScopeConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class Config
{
    private ResourceConnection $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    public function getConfig(string $path, string $scope = IScopeConfig::SCOPE_TYPE_DEFAULT, int $scopeCode = null, bool $parse = false)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('core_config_data');
        if ($scope === 'store') {
            $scope = 'stores';
        } elseif ($scope === 'website') {
            $scope = 'websites';
        }
        $select = $connection->select()->from(
            ['core_config_data' => $tableName],
            ['value']
        )->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeCode);
        $value = $connection->fetchOne($select);
        if (empty($value)) {
            return null;
        }
        if ($parse) {
            $json = \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
            try {
                return $json->unserialize($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }
        return $value;
    }

    public function setConfig(string $path, string $value, string $scope = IScopeConfig::SCOPE_TYPE_DEFAULT, int $scopeCode = null): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('core_config_data');
        if ($scope === 'store') {
            $scope = 'stores';
        } elseif ($scope === 'website') {
            $scope = 'websites';
        }
        if ($scopeCode === null) {
            $scopeCode = 0;
        }
        $select = $connection->select()->from(
            ['core_config_data' => $tableName],
            ['config_id']
        )->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeCode);
        $configId = $connection->fetchOne($select);
        if ($configId) {
            $connection->update(
                $tableName,
                ['value' => $value],
                ['config_id = ?' => $configId]
            );
        } else {
            $connection->insert(
                $tableName,
                [
                    'path' => $path,
                    'value' => $value,
                    'scope' => $scope,
                    'scope_id' => $scopeCode
                ]
            );
        }
    }
}
