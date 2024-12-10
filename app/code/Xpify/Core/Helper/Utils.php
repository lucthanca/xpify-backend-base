<?php
declare(strict_types=1);

namespace Xpify\Core\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

final class Utils
{
    private static ?Uid $uidEncoder = null;
    /**
     * Create HMAC hash based on provided options and secret key.
     *
     * @param array $opts
     * @param string $secret
     * @return string
     */
    public static function createHmac(array $opts, string $secret): string
    {
        // Exclude HMAC from options
        if (isset($opts['hmac'])) {
            unset($opts['hmac']);
        }

        // Setup defaults
        $data = $opts['data'];
        $raw = $opts['raw'] ?? false;
        $buildQuery = $opts['buildQuery'] ?? false;
        $buildQueryWithJoin = $opts['buildQueryWithJoin'] ?? false;
        $encode = $opts['encode'] ?? false;

        if ($buildQuery) {
            //Query params must be sorted and compiled
            ksort($data);
            $queryCompiled = [];
            foreach ($data as $key => $value) {
                $queryCompiled[] = "{$key}=" . (is_array($value) ? implode(',', $value) : $value);
            }
            $data = implode(
                $buildQueryWithJoin ? '&' : '',
                $queryCompiled
            );
        }

        // Create the hmac all based on the secret
        $hmac = hash_hmac('sha256', $data, $secret, $raw);

        // Return based on options
        return $encode ? base64_encode($hmac) : $hmac;
    }

    /**
     * Determines if request is valid by processing secret key through an HMAC-SHA256 hash function
     *
     * @param array  $params array of parameters parsed from a URL
     * @param string $secret the secret key associated with the app in the Partners Dashboard
     *
     * @return bool true if the generated hexdigest is equal to the hmac parameter, false otherwise
     */
    public static function validateHmac(array $params, string $secret): bool
    {
        if (empty($params['hmac']) || empty($secret)) {
            return false;
        }

        return hash_equals(
            $params['hmac'],
            self::createHmac($params, $secret)
        );
    }

    /**
     * @param string $base64Id
     * @return string
     * @throws \Magento\Framework\GraphQl\Exception\GraphQlInputException
     */
    public static function uidToId(string $base64Id): string
    {
        $encoder = self::getUidEncoder();
        $id = $encoder->decode($base64Id);
        if (!$id) {
            throw new \Magento\Framework\GraphQl\Exception\GraphQlInputException(__("Invalid ID!"));
        }
        return $id;
    }

    /**
     * @param string $id
     * @return string
     */
    public static function idToUid(string $id): string
    {
        $encoder = self::getUidEncoder();
        return $encoder->encode($id);
    }

    /**
     * Get the uid encoder.
     *
     * @return Uid
     */
    private static function getUidEncoder(): Uid
    {
        if (!self::$uidEncoder) {
            self::$uidEncoder = \Magento\Framework\App\ObjectManager::getInstance()->get(Uid::class);
        }
        return self::$uidEncoder;
    }

    /**
     * Direct get config from core_config_data without being cached
     *
     * @param string $path
     * @param string $scope
     * @param int|null $scopeCode
     * @param bool $parse - parse the value to array
     * @return string|array|null
     */
    public static function getConfig(string $path, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeCode = null, bool $parse = false)
    {
        $resourceConnection = \Magento\Framework\App\ObjectManager::getInstance()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $tableName = $connection->getTableName('core_config_data');
        if ($scope === 'store') {
            $scope = 'stores';
        } elseif ($scope === 'website') {
            $scope = 'websites';
        }
        $select = $connection->select()->from(
            ['core_config_data' => $tableName],
            ['value']
        )->where(
            'path = ?',
            $path
        )->where(
            'scope = ?',
            $scope
        )->where(
            'scope_id = ?',
            $scopeCode
        );
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

    public static function setConfig(string $path, string $value, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeCode = null): void
    {
        self::setValue($path, $value, $scope, $scopeCode);
    }

    /**
     * Set value to core_config_data
     *
     * @param string $path
     * @param string $value
     * @param string $scope
     * @param int|null $scopeCode
     * @return void
     */
    public static function setValue(string $path, string $value, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeCode = null): void
    {
        $resourceConnection = \Magento\Framework\App\ObjectManager::getInstance()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
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
        )->where(
            'path = ?',
            $path
        )->where(
            'scope = ?',
            $scope
        )->where(
            'scope_id = ?',
            $scopeCode
        );
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
