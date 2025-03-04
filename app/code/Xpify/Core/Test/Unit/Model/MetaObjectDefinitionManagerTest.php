<?php
declare(strict_types=1);

namespace Xpify\Core\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Mockery as m;
use Shopify\Clients\HttpResponse;

class MetaObjectDefinitionManagerTest extends \Xpify\Core\Test\TestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->coreConfigMock = $this->createMock(\Xpify\Core\Helper\Config::class);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testQuăng_lỗi_khi_không_truyền_tham_số_area(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing area');
        new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock);
    }

    public function testQuăng_lỗi_khi_definition_fields_rỗng(): void
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('MetaObject definition fields can not be empty.');
        $definition = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $definition->shouldReceive('getFields')
            ->andReturn([]);
        $mgr->install($this->merchant, $definition);
    }

    public function testQuăng_lỗi_khi_merchant_gỡ_app()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Can not initialize GraphQl Client. Merchant maybe has uninstalled the app.');
        $mgr->install($this->merchant, m::mock(\Xpify\Core\Model\MetaObjectDefinition::class));
    }

    public function testQuăng_lỗi_khi_response_trả_về_errors()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock
            ->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);
        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('existing_definition_id');
        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFailedDefinitionMockResponse(),
            ]));
        $this->expectException(LocalizedException::class);
        $mgr->install($this->merchant, $metaObjectDefinitionMock);
    }

    public function testQuăng_lỗi_khi_response_trả_về_userErrors()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock
            ->shouldReceive('getName')
            ->andReturn('OT: Test Definition 1');
        $metaObjectDefinitionMock
            ->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([]);
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);
        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn(null);
        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'mutation CreateMetaobjectDefinition($definition:');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createUserErrorsMockResponse(),
            ]));
        $this->expectException(LocalizedException::class);
        $mgr->install($this->merchant, $metaObjectDefinitionMock);
    }

    public function testCài_đặt_khi_definition_đã_tồn_tại_và_không_cần_update_gì()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);

        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('existing_definition_id');

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFetchDefinitionMockResponse(),
            ]));

        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([]);
        $metaObjectDefinitionMock->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());

        $result = $mgr->install($this->merchant, $metaObjectDefinitionMock);
        $this->assertEquals('existing_definition_id', $result, 'Should return existing definition id');
    }

    public function testCài_đặt_khi_definition_đã_tồn_tại_và_cần_cập_nhật_khi_storeAccess_thay_đổi()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);

        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('gid://shopify/MetaobjectDefinition/578408816');

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFetchDefinitionMockResponse("NONE"),
            ]));

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'mutation UpdateMetaobjectDefinition($id: ID!');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createUpdateDefinitionMockResponse(),
            ]));

        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([]);
        $metaObjectDefinitionMock->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());

        $result = $mgr->install($this->merchant, $metaObjectDefinitionMock);
        $this->assertEquals('gid://shopify/MetaobjectDefinition/578408816', $result, 'Should return existing definition id');
    }

    public function testCài_đặt_khi_definition_đã_tồn_tại_và_cần_cập_nhật_khi_validations_thay_đổi()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);

        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('gid://shopify/MetaobjectDefinition/578408816');

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFetchDefinitionMockResponse(),
            ]));

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'mutation UpdateMetaobjectDefinition($id: ID!');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createUpdateDefinitionMockResponse(),
            ]));

        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([]);
        $metaObjectDefinitionMock->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData(withValidations: true));

        $result = $mgr->install($this->merchant, $metaObjectDefinitionMock);
        $this->assertEquals('gid://shopify/MetaobjectDefinition/578408816', $result, 'Should return existing definition id');
    }

    public function testCài_đặt_khi_definition_đã_tồn_tại_và_cần_cập_nhật_khi_capabilities_thay_đổi()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);

        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('gid://shopify/MetaobjectDefinition/578408816');

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFetchDefinitionMockResponse(publishableEnabled: true),
            ]));

        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'mutation UpdateMetaobjectDefinition($id: ID!');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createUpdateDefinitionMockResponse(),
            ]));

        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([
                'publishable' => [
                    'enabled' => false,
                ]
            ]);
        $metaObjectDefinitionMock->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());

        $result = $mgr->install($this->merchant, $metaObjectDefinitionMock);
        $this->assertEquals('gid://shopify/MetaobjectDefinition/578408816', $result, 'Should return existing definition id');
    }

    public function testCài_đặt_khi_definition_đã_tồn_tại_nhưng_sẽ_tạo_mới_definition_vì_đã_bị_xoá_trên_admin_shopify()
    {
        $mgr = new \Xpify\Core\Model\MetaObjectDefinitionManager($this->coreConfigMock, 'test_area');
        $metaObjectDefinitionMock = m::mock(\Xpify\Core\Model\MetaObjectDefinition::class);
        $graphQlMock = m::mock(\Shopify\Clients\Graphql::class);
        $this->merchant->expects($this->once())
            ->method('getGraphql')
            ->willReturn($graphQlMock);
        $this->coreConfigMock->expects($this->once())
            ->method('getConfig')
            ->willReturn('existing_definition_id');
        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'query GetMetaObjectDefinition');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createFetchDefinitionMockResponse(isEmpty: true),
            ]));
        $graphQlMock->shouldReceive('query')
            ->once()
            ->with(m::on(function ($payload) {
                return str_contains($payload['query'], 'mutation CreateMetaobjectDefinition($definition:');
            }))->andReturn(m::mock(HttpResponse::class, [
                "getDecodedBody" => $this->createCreateDefinitionMockResponse(),
            ]));
        $metaObjectDefinitionMock
            ->shouldReceive('getType')
            ->andReturn('test_type');
        $metaObjectDefinitionMock->shouldReceive('getName')
            ->andReturn('OT: Test Definition 1');
        $metaObjectDefinitionMock->shouldReceive('getAccess')
            ->andReturn([
                'storefront' => 'PUBLIC_READ',
            ]);
        $metaObjectDefinitionMock->shouldReceive('getCapabilities')
            ->andReturn([]);
        $metaObjectDefinitionMock->shouldReceive('getFields')
            ->andReturn($this->createDefinitionMockData());
        $this->coreConfigMock->expects($this->once())
            ->method('setConfig')
            ->with(true, $this->callback(function ($value) {
                if ($value !== 'gid://shopify/MetaobjectDefinition/578408816') {
                    throw new \PHPUnit\Framework\ExpectationFailedException('Should set new definition id');
                }
                return true;
            }));
        $result = $mgr->install($this->merchant, $metaObjectDefinitionMock);
        $this->assertEquals('gid://shopify/MetaobjectDefinition/578408816', $result, 'Should return new definition id');
    }

    private function createDefinitionMockData(bool $withValidations = false): array
    {
        $validations = [];
        if ($withValidations) {
            $validations = [
                [
                    "name" => "metaobject_definition_id",
                    "value" => "gid://shopify/MetaobjectDefinition/578408815",
                ]
            ];
        }
        return [
            'ask' => [
                'name' => 'Ask',
                'type' => 'single_line_text_field',
                'key' => 'ask',
            ],
            'answer' => [
                'name' => 'Answer',
                'type' => 'multi_line_text_field',
                'key' => 'answer',
                'validations' => $validations,
            ],
        ];
    }

    private function createFetchDefinitionMockResponse(string $accessStorefront = "PUBLIC_READ", bool $isEmpty = false, $publishableEnabled = false): array
    {
        if ($isEmpty) {
            return [
                "data" => [
                    "metaobjectDefinition" => null,
                ],
            ];
        }
        return [
            "data" => [
                "metaobjectDefinition" => [
                    "access" => [
                        "storefront" => $accessStorefront,
                    ],
                    "type" => "test_type",
                    "fieldDefinitions" => [
                        [
                            "key" => "ask",
                            "name" => "Ask",
                            "type" => [
                                "name" => "single_line_text_field",
                            ],
                            "validations" => [],
                        ],
                        [
                            "key" => "answer",
                            "name" => "Answer",
                            "type" => [
                                "name" => "multi_line_text_field",
                            ],
                            "validations" => [],
                        ],
                    ],
                    "capabilities" => [
                        "publishable" => [
                            "enabled" => $publishableEnabled,
                        ]
                    ],
                ],
            ],
        ];
    }

    private function createUpdateDefinitionMockResponse(): array
    {
        return [
            "data" => [
                "metaobjectDefinitionUpdate" => [
                    "metaobjectDefinition" => [
                        "id" => "gid://shopify/MetaobjectDefinition/578408816",
                        "name" => "OT: Test Definition 1",
                        "type" => "test_type",
                        "fieldDefinitions" => [],
                    ],
                ],
            ],
        ];
    }

    private function createCreateDefinitionMockResponse(): array
    {
        return [
            "data" => [
                "metaobjectDefinitionCreate" => [
                    "metaobjectDefinition" => [
                        "id" => "gid://shopify/MetaobjectDefinition/578408816",
                        "name" => "OT: Test Definition 1",
                        "type" => "test_type",
                        "fieldDefinitions" => [
                            [
                                "key" => "ask",
                                "name" => "Ask",
                            ],
                            [
                                "key" => "answer",
                                "name" => "Answer",
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createFailedDefinitionMockResponse(): array
    {
        return [
            "errors" => [
                [
                    "message" => "Invalid ....",
                    "locations" => [
                        [
                            "line" => 2,
                            "column" => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createUserErrorsMockResponse(): array
    {
        return [
            "data" => [
                "metaobjectDefinitionCreate" => [
                    "metaobjectDefinition" => null,
                    "userErrors" => [
                        [
                            "code" => "INVALID_TYPE",
                            "field" => "type",
                            "message" => "The metafield type is invalid.",
                        ],
                    ],
                ],
            ],
        ];
    }
}
