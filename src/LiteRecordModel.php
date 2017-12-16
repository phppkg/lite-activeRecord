<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 16/9/1
 * Time: 下午4:13
 */

namespace SimpleAR;

use Inhere\Exceptions\InvalidArgumentException;
use Inhere\Exceptions\InvalidConfigException;
use Inhere\Library\Collections\SimpleCollection;
use Inhere\LiteDb\ExtendedPdo;

/**
 * Class RecordModel
 * @package SimpleAR
 */
abstract class LiteRecordModel extends Model
{
    // use RecordModelExtraTrait;

    /**
     * @var array
     */
    private $_backup = [];

    /**
     * 发生改变的数据
     * @var array
     */
    private $changes = [];

    const SCENE_DEFAULT = 'default';
    const SCENE_CREATE = 'create';
    const SCENE_UPDATE = 'update';
    const SCENE_DELETE = 'delete';
    const SCENE_SEARCH = 'search';

    /**
     * the table primary key name
     * @var string
     */
    protected static $pkName = 'id';

    /**
     * current table name alias
     * 'mt' -- main table
     * @var string
     */
    protected static $aliasName = 'mt';

    /**
     * the table name
     * @var string
     */
    private static $tableName;

    /**
     * @var array
     */
    protected static $defaultOptions = [
        /* data index column. */
        'indexKey' => null,
        /*
        data type, in :
            'model'      -- return object, instanceof `self`
            '\\stdClass' -- return object, instanceof `stdClass`
            'array'      -- return array, only  [ column's value ]
            'assoc'      -- return array, Contain  [ column's name => column's value]
        */
        'fetchType' => 'model',

        // 追加限制
        // e.g:
        //  'limit' => [10, 120],
        //  'order' => 'createTime ASC',
        //  'group' => 'id, type',

        // can be a closure
        // function () { ... }
    ];

    /**
     * default only update the have been changed column.
     * @var bool
     */
    protected $onlyUpdateChanged = true;

    /**
     * Are you allowed to insert the primary key?
     * @var boolean
     */
    protected $allowInsertPk = true;

    /**
     * @param $data
     * @param string $scene
     * @return static
     */
    public static function load($data, $scene = '')
    {
        return new static($data, $scene);
    }

    /**
     * RecordModel constructor.
     * @param array $items
     * @param string $scene
     * @throws InvalidConfigException
     */
    public function __construct(array $items = [], string $scene = '')
    {
        parent::__construct($items);

        $this->scene = trim($scene);

        if (!$this->getColumns()) {
            throw new InvalidConfigException('Must define method columns() and cannot be empty.');
        }

        self::getTableName();
    }

    /***********************************************************************************
     * some prepare work
     ***********************************************************************************/

    /**
     * TODO 定义保存数据时,当前场景允许写入的属性字段
     * @return array
     */
    public function scenarios()
    {
        return [
            // 'create' => ['username', 'email', 'password','createTime'],
            // 'update' => ['username', 'email','createTime'],
        ];
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        // default is current class name
        $name = lcfirst(basename(str_replace('\\', '/', static::class)));

        if (\substr($name, -5) === 'Model') {
            $name = substr($name, 0, -5);
        }

        // '{@pfx}' -- is table prefix placeholder
        // return '{@pfx}articles';
        // if no table prefix
        // return 'articles';

        return '{@pfx}' . $name;
    }

    /**
     * if {@see static::$aliasName} not empty, return `tableName AS aliasName`
     * @return string
     */
    final public static function queryName()
    {
        self::getTableName();

        return static::$aliasName ? self::$tableName . ' AS ' . static::$aliasName : self::$tableName;
    }

    /**
     * the database driver instance
     * @return ExtendedPdo
     */
    abstract public static function getDb();

    /**
     * getTableName
     * @return string
     */
    final public static function getTableName()
    {
        if (!self::$tableName) {
            self::$tableName = static::tableName();
        }

        return self::$tableName;
    }

    /***********************************************************************************
     * find operation
     ***********************************************************************************/

    private $loadFromDb = false;

    /**
     * find record by primary key
     * @param string|int|array $pkValue
     * @param  string|array $select
     * @param  array $options
     * @return static
     */
    public static function findByPk($pkValue, string $select = '*', array $options = [])
    {
        // only one
        $wheres = [static::$pkName => $pkValue];

        if (\is_array($pkValue)) {// many
            $wheres = [static::$pkName, 'IN', $pkValue];
        }

        return static::findOne($wheres, $select, $options);
    }

    /**
     * find a record by where condition
     * @param mixed $wheres
     * @param string|array $select
     * @param array $options
     * @return static
     */
    public static function findOne($wheres, string $select = '*', array $options = [])
    {
        $options = array_merge(static::$defaultOptions, $options);

        if ($isModel = ($options['fetchType'] === 'model')) {
            $options['class'] = static::class;
        }

        $model = static::getDb()->findOne(self::getTableName(), $wheres, $select, $options);

        // use data model
        if ($model && $isModel) {
            /** @var static $model */
            $model->setLoadFromDb();
        }

        return $model;
    }

    /**
     * @param mixed $wheres {@see self::handleConditions() }
     * @param string|array $select
     * @param array $options
     * @return array
     */
    public static function findAll($wheres, string $select = '*', array $options = [])
    {
        $options = array_merge(static::$defaultOptions, ['fetchType' => 'assoc'], $options);

        if ($options['fetchType'] === 'model') {
            $options['class'] = static::class;
        }

        return static::getDb()->findAll(self::getTableName(), $wheres, $select, $options);
    }

    /***********************************************************************************
     * create/update operation
     ***********************************************************************************/

    /**
     * @param array $updateColumns
     * @param bool|false $updateNulls
     * @return bool
     * @throws InvalidArgumentException
     */
    public function save(array $updateColumns = [], $updateNulls = false)
    {
        $this->isNew() ? $this->insert() : $this->update($updateColumns, $updateNulls);

        return !$this->hasError();
    }

    /**
     * @return static
     */
    public function insert()
    {
        // check primary key
        if (!$this->allowInsertPk && $this->pkValue()) {
            throw new \RuntimeException('The primary key value is exists, cannot run the method insert()');
        }

        $this->beforeInsert();
        $this->beforeSave();

        if ($this->enableValidate && $this->validate()->fail()) {
            return $this;
        }

        // when insert successful.
        if ($pkValue = static::getDb()->insert(self::getTableName(), $this->getColumnsData())) {
            $this->set(static::$pkName, $pkValue);
            $this->setLoadFromDb();

            $this->afterInsert();
            $this->afterSave();
        }

        return $this;
    }

    /**
     * update by primary key
     * @param array $updateColumns only update some columns
     * @param bool $updateNulls
     * @return static
     * @throws InvalidArgumentException
     */
    public function update(array $updateColumns = [], $updateNulls = false)
    {
        // check primary key
        if (!$pkValue = $this->pkValue()) {
            throw new InvalidArgumentException('The primary value is cannot be empty for update the model');
        }

        $this->beforeUpdate();
        $this->beforeSave();

        $result = true;
        $pkName = static::$pkName;
        $validateColumns = $updateColumns;

        // the primary column is must be exists for defined validate.
        if ($validateColumns && !\in_array($pkName, $validateColumns, true)) {
            $validateColumns[] = $pkName;
        }

        // validate data
        if ($this->enableValidate && $this->validate($validateColumns)->fail()) {
            return $this;
        }

        // collect there are data will update.
        if ($this->onlyUpdateChanged) {
            // Exclude the column if it value not change
            $data = array_filter($this->getColumnsData(), function ($col) {
                return $this->valueIsChanged($col);
            }, ARRAY_FILTER_USE_KEY);
        } elseif ($updateColumns) {
            $all = $this->getColumnsData();
            $data = [];

            foreach ($updateColumns as $column) {
                if (array_key_exists($column, $all)) {
                    $data[$column] = $all[$column];
                }
            }
        } else {
            $data = $this->getColumnsData();

            if (!$updateNulls) {
                $data = array_filter($data, function ($val) {
                    return $val !== null;
                });
            }
        }

        unset($data[$pkName]);

        // only exec on the data is not empty.
        if ($data) {
            $result = static::getDb()->update(self::getTableName(), [$pkName => $pkValue], $data);
        }

        if ($result) {
            $this->changes = []; // reset
            $this->afterUpdate();
            $this->afterSave();
        }

        unset($data);

        return $this;
    }

    /***********************************************************************************
     * delete operation
     ***********************************************************************************/

    /**
     * delete by model
     * @return int
     */
    public function delete()
    {
        if (!$pkValue = $this->pkValue()) {
            return 0;
        }

        $this->beforeDelete();

        if ($affected = self::deleteByPk($pkValue)) {
            $this->afterDelete();
        }

        return $affected;
    }

    /**
     * @param int|array $pkValue
     * @return int
     */
    public static function deleteByPk($pkValue)
    {
        // only one
        $where = [static::$pkName => $pkValue];

        // many
        if (\is_array($pkValue)) {
            $where = [static::$pkName, 'IN', $pkValue];
        }

        return self::deleteBy($where);
    }

    /**
     * @param mixed $wheres
     * @return int
     */
    public static function deleteBy($wheres)
    {
        return static::getDb()->delete(self::getTableName(), $wheres);
    }

    /***********************************************************************************
     * transaction operation
     ***********************************************************************************/

    /**
     * @return bool
     */
    public static function beginTransaction()
    {
        return static::getDb()->beginTransaction();
    }

    /**
     * @return bool
     */
    public static function commit()
    {
        return static::getDb()->commit();
    }

    /**
     * @return bool
     */
    public static function rollBack()
    {
        return static::getDb()->rollBack();
    }

    /**
     * @return bool
     */
    public static function inTransaction()
    {
        return static::getDb()->inTransaction();
    }

    /***********************************************************************************
     * extra operation
     ***********************************************************************************/

    protected function beforeInsert()
    {
        return true;
    }

    protected function afterInsert()
    {
    }

    protected function beforeUpdate()
    {
        return true;
    }

    protected function afterUpdate()
    {
    }

    protected function beforeSave()
    {
        return true;
    }

    protected function afterSave()
    {
    }

    protected function beforeDelete()
    {
        return true;
    }

    protected function afterDelete()
    {
    }

    /***********************************************************************************
     * helper method
     ***********************************************************************************/

    /**
     * @param string $column
     * @param mixed $value
     * @return $this|SimpleCollection
     */
    public function set($column, $value)
    {
        // on change, save old value
        if ($this->loadFromDb && $this->hasColumn($column)) {
            $this->setChange($column, $this->get($column));
        }

        return parent::set($column, $value);
    }

    /**
     * @return bool
     */
    public function isNew()
    {
        return !$this->get(static::$pkName, false);
    }

    /**
     * @param null|bool $value
     * @return bool
     */
    public function enableValidate($value = null)
    {
        if (\is_bool($value)) {
            $this->enableValidate = $value;
        }

        return $this->enableValidate;
    }

    /**
     * @return string|int
     */
    public function pkName()
    {
        return static::$pkName;
    }

    /**
     * @return mixed
     */
    public function pkValue()
    {
        return $this->get(static::$pkName);
    }

    /**
     * Check whether the column's value is changed, the update.
     * @param string $column
     * @return bool
     */
    protected function valueIsChanged($column)
    {
        return $this->isNew() ||
            (isset($this->changes[$column]) && $this->get($column) !== $this->getChange($column));
    }

    /**
     * @return bool
     */
    public function isLoadFromDb()
    {
        return $this->loadFromDb;
    }

    /**
     * @param bool $value
     */
    public function setLoadFromDb($value = true)
    {
        $this->loadFromDb = (bool)$value;
    }

    /**
     * @return array
     */
    public function getOldData()
    {
        return $this->_backup;
    }

    /**
     * @param $data
     */
    public function setOldData($data)
    {
        $this->_backup = $data;
    }

    /**
     * @param $column
     * @return mixed
     */
    public function getOld($column)
    {
        return $this->_backup[$column] ?? null;
    }

    /**
     * @return array
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param array $changes
     */
    public function setChanges(array $changes)
    {
        $this->changes = $changes;
    }

    /**
     * @param string $column
     * @return mixed
     */
    public function getChange($column)
    {
        return $this->changes[$column] ?? null;
    }

    /**
     * @param string $column
     * @param mixed $value
     */
    public function setChange($column, $value)
    {
        if ($this->hasColumn($column)) {
            $this->changes[$column] = $value;
        }
    }
}
