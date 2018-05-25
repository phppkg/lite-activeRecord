<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/26 0026
 * Time: 02:10
 */

namespace SimpleAR;

use Inhere\Validate\ValidationTrait;

/**
 * Trait ModelTrait
 * @package SimpleAR
 */
trait ModelTrait
{
    use ValidationTrait;

    /**
     * @var array
     */
    private static $_models = [];

    /**
     * @var bool
     */
    protected $enableValidate = true;

    /**
     * if true, will only save(insert/update) safe's data -- Through validation's data
     * @var bool
     */
    protected $onlySaveSafeData = true;

    /**
     * The columns of the model
     * @var array
     */
    private $columns;

    /**
     * @param string $class
     * @return static
     * @throws \RuntimeException
     */
    public static function model(string $class = '')
    {
        $class = $class ?: static::class;

        if (!isset(self::$_models[$class]) || !(self::$_models[$class] instanceof self)) {
            $model = new $class;

            if (!($model instanceof self)) {
                throw new \RuntimeException("The model class [$class] must instanceof " . self::class);
            }

            self::$_models[$class] = $model;
        }

        return self::$_models[$class];
    }

    /**
     * @param $data
     * @return static
     */
    public static function load($data)
    {
        return new static($data);
    }

    /**
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);

        $this->columns = $this->columns();
    }

    /**
     * define model field list
     * @return array
     */
    public function columns(): array
    {
        return [
            /*
                   // column => type
                   'id'          => 'int',
                   'title'       => 'string',
                   'createTime'  => 'int',
             */
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function translates(): array
    {
        return [
            // 'field' => 'translate',
            // e.g. 'name'=>'名称',
        ];
    }

    /**
     * format column's data type
     * @param $column
     * @param $value
     * @return $this|SimpleCollection
     */
    public function set($column, $value)
    {
        // belong to the model.
        if (isset($this->columns[$column])) {
            $value = $this->convertType($value, $this->columns[$column]);
        }

        return parent::set($column, $value);
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return int
     */
    protected function convertType($value, string $type): int
    {
        if ($type === Type::INT) {
            $value = (int)$value;
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getColumnsData(): array
    {
        $source = $this->onlySaveSafeData ? $this->getSafeData() : $this;
        $data = [];

        foreach ($source as $col => $val) {
            if (isset($this->columns[$col])) {
                $data[$col] = $val;
            }
        }

        return $data;
    }

    /**
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $column): bool
    {
        return isset($this->columns[$column]);
    }

    /**
     * @return bool
     */
    public function isEnableValidate(): bool
    {
        return $this->enableValidate;
    }

    /**
     * @return bool
     */
    public function isOnlySaveSafeData(): bool
    {
        return $this->onlySaveSafeData;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
