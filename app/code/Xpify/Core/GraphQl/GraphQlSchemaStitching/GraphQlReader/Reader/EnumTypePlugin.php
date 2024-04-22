<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl\GraphQlSchemaStitching\GraphQlReader\Reader;

use Xpify\Core\GraphQl\AppDirectiveReader;

class EnumTypePlugin
{
    public function afterRead($subject, $result, \GraphQL\Type\Definition\Type $typeMeta)
    {
        if (!empty($result)) {
            $appDirectiveValue = AppDirectiveReader::read($typeMeta->astNode->directives);
            if ($appDirectiveValue) {
                $result['app'] = $appDirectiveValue;
            }
        }
        return $result;
    }
}
