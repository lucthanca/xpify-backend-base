<?php
declare(strict_types=1);

namespace Xpify\Auth\Service;

use Magento\Framework\GraphQl\Query\Uid;
use Shopify\Auth\OAuth;
use Shopify\Context;
use Shopify\Exception\CookieSetException;
use Shopify\Exception\PrivateAppException;
use Shopify\Exception\SessionStorageException;
use Shopify\Exception\UninitializedContextException;
use Xpify\App\Api\Data\AppInterface as IApp;

class AuthRedirection
{
    private Uid $uidEncoder;

    /**
     * @param Uid $uidEncoder
     */
    public function __construct(
        Uid $uidEncoder
    ) {
        $this->uidEncoder = $uidEncoder;
    }

    /**
     * Use Shopify OAuth to create authorize URL
     *
     * @param IApp $app
     * @param string $shop
     * @param bool $isOnline
     * @return string
     * @throws CookieSetException
     * @throws PrivateAppException
     * @throws SessionStorageException
     * @throws UninitializedContextException
     */
    public function createRedirectUrl(IApp $app, string $shop, bool $isOnline = IApp::DEFAULT_ACCESS_MODE === IApp::ACCESS_MODE_ONLINE): string
    {
        return OAuth::begin(
            $shop,
            'api/auth/callback/_rid/' . $app->getRemoteId(),
            $isOnline,
            ['Xpify\Auth\Service\CookieHandler', 'saveShopifyCookie'],
        );
    }

    /**
     *  Phương thức này được sử dụng để xây dựng URI chuyển hướng cho ứng dụng Shopify.
     *  Đầu tiên, nó tạo một chữ ký HMAC bằng cách sử dụng ID ứng dụng và các tham số truy vấn.
     *  Chữ ký HMAC được tạo bằng cách sử dụng khóa bí mật hệ thống.
     *  Sau đó, nó lấy tên host của ứng dụng từ ngữ cảnh.
     *  URI chuyển hướng được xây dựng bằng cách sử dụng tên host, ID ứng dụng, tên miền cửa hàng từ các tham số truy vấn, và chữ ký HMAC.
     *  URI chuyển hướng sau đó được mã hóa URL.
     *  Cuối cùng, URI chuyển hướng được thêm vào các tham số truy vấn và trả về dưới dạng chuỗi truy vấn URL.
     *
     * @param string $shop
     * @param int $appId
     * @return string
     */
    public function createAuthUrl(string $shop, int $appId)
    {
        $sign = \Xpify\Core\Helper\Utils::createHmac([
            'data' => ['shop' => $shop, '_i' => $appId],
            'buildQuery' => true,
            'buildQueryWithJoin' => true,
        ], \Xpify\Core\Model\Constants::SYS_SECRET_KEY);
        $appHost = Context::$HOST_NAME;
        return "https://$appHost/api/auth?shop=$shop&_i={$this->uidEncoder->encode((string) $appId)}&sign=$sign";
    }
}
