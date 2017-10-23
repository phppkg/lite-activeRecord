<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-10-23
 * Time: 17:26
 */

namespace SimpleAR\Builders;

/**
 * Class AbstractWhere
 * @package SimpleAR\Builders
 */
abstract class AbstractWhereFragment
{
    /**
     * Tokens for nested OR and AND conditions.
     */
    const TOKEN_AND = '@AND';
    const TOKEN_OR  = '@OR';
}