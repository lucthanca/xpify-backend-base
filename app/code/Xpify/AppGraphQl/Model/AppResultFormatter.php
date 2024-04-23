<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model;

use Magento\Framework\GraphQl\Query\Uid;
use Xpify\App\Api\Data\AppInterface as IApp;

class AppResultFormatter
{
    private $uidEncoder;

    /**
     * @param Uid $uidEncoder
     */
    public function __construct(
        Uid $uidEncoder
    ) {
        $this->uidEncoder = $uidEncoder;
    }

    /**
     * To Array
     *
     * @param IApp $app
     * @return array
     */
    public function toGraphQlOutput(IApp $app): array
    {
        return [
            'id' => $this->uidEncoder->encode((string) $app->getId()),
            IApp::REMOTE_ID => $app->getRemoteId(),
            IApp::NAME => $app->getName(),
            IApp::API_KEY => $app->getApiKey(),
            IApp::SECRET_KEY => $app->getSecretKey(),
            IApp::CREATED_AT => $app->getCreatedAt(),
            IApp::SCOPES => $app->getScopes(),
            IApp::API_VERSION => $app->getApiVersion(),
            IApp::HANDLE => $app->getHandle(),
            IApp::TOKEN => $app->getToken(),
        ];
    }
}
