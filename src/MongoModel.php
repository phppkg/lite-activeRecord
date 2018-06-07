<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-12-14
 * Time: 11:42
 */

namespace SimpleAR;

use Inhere\LiteDb\LiteMongo;
use Toolkit\Collection\SimpleCollection;

/**
 * Class MongoModel
 * @package SimpleAR
 */
abstract class MongoModel extends SimpleCollection implements RecordModelInterface
{
    use ModelTrait;

    /**
     * @var string The collection name
     */
    private $collectionName;

    /**
     * the database driver instance
     * @return LiteMongo
     */
    abstract public static function getDb(): LiteMongo;

    /**
     * @param array $data
     * @param string $scene
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function load(array $data, string $scene = '')
    {
        return new static($data, $scene);
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        // default is current class name
        $name = \lcfirst(\basename(\str_replace('\\', '/', static::class)));
        if (\substr($name, -5) === 'Model') {
            $name = \substr($name, 0, -5);
        }

        // '{@pfx}' -- is table prefix placeholder
        // return '{@pfx}articles';
        // if no table prefix
        // return 'articles';
        return '{@pfx}' . $name;
    }

    /**
     * get collection name
     * @return string
     */
    final public static function collectionName(): string
    {
        return static::tableName();
    }

    /**
     * RecordModel constructor.
     * @param array $items
     * @param string $scene
     * @throws \InvalidArgumentException
     */
    public function __construct(array $items = [], string $scene = '')
    {
        parent::__construct($items);

        $this->scene = \trim($scene);
        $this->columns = $this->columns();

        if (!$this->getColumns()) {
            throw new \InvalidArgumentException('Must define method columns() and cannot be empty.');
        }

        $this->collectionName = static::tableName();
    }

    /**
     * @return string
     */
    public function getCollectionName(): string
    {
        return $this->collectionName;
    }
}
