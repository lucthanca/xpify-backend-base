<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl\GraphQlSchemaStitching\GraphQlReader\MetaReader;

use Xpify\Core\GraphQl\AppDirectiveReader;

class FieldMetaReaderPlugin
{
    public function afterRead($subject, $result, \GraphQL\Type\Definition\FieldDefinition $fieldMeta) : array
    {
        $appDirectiveValue = AppDirectiveReader::read($fieldMeta->astNode->directives);
        if ($appDirectiveValue) {
            $result['app'] = $appDirectiveValue;
        }
        return $result;
    }
}
