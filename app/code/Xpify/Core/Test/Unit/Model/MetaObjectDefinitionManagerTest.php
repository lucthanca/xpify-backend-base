<?php
declare(strict_types=1);

namespace Xpify\Core\Test\Unit\Model;

class MetaObjectDefinitionManagerTest extends \Xpify\Core\Test\TestAbstract
{
    public function testThrow_exception_when_no_area(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing area');
        new \Xpify\Core\Model\MetaObjectDefinitionManager();
    }
}
