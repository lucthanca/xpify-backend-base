<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

final class AppDirectiveReader
{
    public static function read($directives)
    {
        foreach ($directives as $directive) {
            if ($directive->name->value == 'app') {
                foreach ($directive->arguments as $directiveArgument) {
                    if ($directiveArgument->name->value == 'id') {
                        return $directiveArgument->value->value;
                    }
                }
            }
        }
        return null;
    }
}
