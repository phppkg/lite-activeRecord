<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2016/2/19 0019
 * Time: 23:35
 */

namespace SimpleAR;

use Toolkit\Collection\SimpleCollection;

/**
 * Class Model
 * @package SimpleAR\Model
 */
class Model extends SimpleCollection
{
    use ModelTrait;

    /**
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);

        $this->columns = $this->columns();
    }

}
