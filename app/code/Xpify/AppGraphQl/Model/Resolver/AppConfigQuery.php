<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface as IResolver;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\Core\Helper\Utils;
use Xpify\App\Api\Data\AppInterface as IApp;

class AppConfigQuery implements IResolver
{
    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        /** @var IApp $app */
        $app = $context->getExtensionAttributes()->getApp();
        if (!$app?->getId()) {
            return null;
        }
        return [
            'model' => $app,
            'id' => Utils::idToUid($app->getId() . ""),
            'env' => $app->isProd() ? 'PROD' : 'DEV',
        ];
    }
}
