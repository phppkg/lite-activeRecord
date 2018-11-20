<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2016/2/19 0019
 * Time: 23:35
 */

namespace PhpComp\LiteActiveRecord;

use Toolkit\Collection\SimpleCollection;

/**
 * Class Model
 * @package PhpComp\LiteActiveRecord\Model
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
