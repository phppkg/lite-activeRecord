<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/10/22
 * Time: 上午11:33
 */

namespace PhpComp\LiteActiveRecord;

/**
 * Class DbHelper
 * @package PhpComp\LiteActiveRecord
 */
class Helper
{
    /**
     * @param string $name
     * @param string $quoteChar
     * @return array
     */
    public static function resolveName(string $name, string $quoteChar = '`'): array
    {
        $parts = \explode('.', \str_replace($quoteChar, '', $name), 2);

        if (isset($parts[1])) {
            return $parts;
        }

        return [null, $name];
    }
}
