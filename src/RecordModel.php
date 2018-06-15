<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 16/9/1
 * Time: 下午4:13
 */

namespace SimpleAR;

use Toolkit\Collection\SimpleCollection;
use Inhere\LiteDb\LitePdo;

/**
 * Class RecordModel
 * @package SimpleAR
 */
abstract class RecordModel extends SimpleCollection implements RecordModelInterface
{
    use ModelTrait;

    /**
     * @var array Data backup
     */
    private $_backup = [];

    /**
     * @var array Changed data
     */
    private $_changes = [];

    /**
     * @var string The table primary key name. default is 'id'
     */
    private $pkField;

    /**
     * @var string The table name
     */
    private $tableName;

    /**
     * @var string Current table name alias 'mt' -- main table
     */
    protected static $aliasName = 'mt';

    /**
     * @var array
     */
    protected static $defaultOptions = [
        /* data index column. */
        'indexKey' => null,

        /*
        data type, allowed :
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
     * @throws \InvalidArgumentException
     */
    public static function load($data, string $scene = '')
    {
        return new static($data, $scene);
    }

    /**
     * @return string
     */
    public static function pkField(): string
    {
        return 'id';
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
     * if {@see static::$aliasName} not empty, return `tableName AS aliasName`
     * @return string
     */
    final public static function queryName(): string
    {
        $table = static::tableName();

        return static::$aliasName ? $table . ' AS ' . static::$aliasName : $table;
    }

    /**
     * the database driver instance
     * @return LitePdo
     */
    abstract public static function getDb(): LitePdo;

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

        $this->pkField = static::pkField();
        $this->tableName = static::tableName();
    }

    /***********************************************************************************
     * some prepare work
     ***********************************************************************************/

    /**
     * TODO 定义保存数据时,当前场景允许写入的属性字段
     * @return array
     */
    public function scenarios(): array
    {
        return [
            // 'create' => ['username', 'email', 'password','createTime'],
            // 'update' => ['username', 'email','createTime'],
        ];
    }

    /***********************************************************************************
     * find operation
     ***********************************************************************************/

    /** @var bool */
    private $loadFromDb = false;

    /**
     * find record by primary key
     * @param string|int|array $pkValue
     * @param  string|array $select
     * @param  array $options
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function findByPk($pkValue, string $select = '*', array $options = [])
    {
        // only one
        $wheres = [static::pkField() => $pkValue];

        if (\is_array($pkValue)) {// many
            $wheres = [static::pkField(), 'IN', $pkValue];
        }

        return static::findOne($wheres, $select, $options);
    }

    /**
     * find a record by where condition
     * @param mixed $wheres {@see \Inhere\LiteDb\Helper\DBHelper::handleConditions() }
     * @param string|array $select
     * @param array $options
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function findOne($wheres, string $select = '*', array $options = [])
    {
        $options = \array_merge(static::$defaultOptions, $options);

        if ($isModel = ($options['fetchType'] === 'model')) {
            $options['class'] = static::class;
        }

        $table = static::tableName();

        if ($alias = $options['tableAlias'] ?? null) {
            $table .= ' AS ' . $alias;
        }

        $model = static::getDb()->queryOne($table, $wheres, $select, $options);

        // use data model
        if ($model && $isModel) {
            /** @var static $model */
            $model->setLoadFromDb();
        }

        return $model;
    }

    /**
     * @param mixed $wheres {@see \Inhere\LiteDb\Helper\DBHelper::handleConditions() }
     * @param string|array $select
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function findAll($wheres, string $select = '*', array $options = []): array
    {
        $options = \array_merge(static::$defaultOptions, ['fetchType' => 'assoc'], $options);

        if ($options['fetchType'] === 'model') {
            $options['class'] = static::class;
        }

        $table = static::tableName();

        if ($alias = $options['tableAlias'] ?? null) {
            $table .= ' AS ' . $alias;
        }

        return static::getDb()->queryAll($table, $wheres, $select, $options);
    }

    /**
     * @param mixed $wheres {@see \Inhere\LiteDb\Helper\DBHelper::handleConditions() }
     * @return int
     * @throws \InvalidArgumentException
     */
    public static function counts($wheres = null): int
    {
        return static::getDb()->count(static::tableName(), $wheres);
    }

    /***********************************************************************************
     * create/update operation
     ***********************************************************************************/

    /**
     * @param array $updateColumns
     * @param bool|false $updateNulls
     * @return bool
     * @throws \RuntimeException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function save(array $updateColumns = [], $updateNulls = false): bool
    {
        $this->isNew() ? $this->insert() : $this->update($updateColumns, $updateNulls);

        return !$this->hasError();
    }

    /**
     * @return static
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function insert(): self
    {
        // check primary key
        if (!$this->allowInsertPk && $this->pkValue()) {
            throw new \RuntimeException('The primary key value is exists, cannot run the method insert()');
        }

        $this->beforeInsert();
        $this->beforeSave();

        // validate failure
        if ($this->enableValidate && $this->validate()->fail()) {
            return $this;
        }

        // when insert successful.
        if ($pkValue = static::getDb()->insert(static::tableName(), $this->getColumnsData())) {
            $this->set($this->pkField, $pkValue);
            $this->setLoadFromDb();

            $this->afterInsert();
            $this->afterSave();
        } else {
            $this->addError('data-insert', 'create new record is failure');
        }

        return $this;
    }

    /**
     * update by primary key
     * @param array $updateColumns only update some columns
     * @param bool $updateNulls
     * @return static
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function update(array $updateColumns = [], $updateNulls = false)
    {
        // check primary key
        if (!$pkValue = $this->pkValue()) {
            throw new \InvalidArgumentException('The primary value is cannot be empty for update the model');
        }

        $this->beforeUpdate();
        $this->beforeSave();

        $pkField = $this->pkField;
        $validateColumns = $updateColumns;

        // the primary column is must be exists for defined validate.
        if ($validateColumns && !\in_array($pkField, $validateColumns, true)) {
            $validateColumns[] = $pkField;
        }

        // validate data
        if ($this->enableValidate && $this->validate($validateColumns)->fail()) {
            return $this;
        }

        // collect there are data will update.
        if ($this->onlyUpdateChanged) {
            // Exclude the column if it value not change
            $data = \array_filter($this->getColumnsData(), function ($col) {
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

        unset($data[$pkField]);

        // only exec on the data is not empty.
        if ($data) {
            $result = static::getDb()->update(static::tableName(), [$pkField => $pkValue], $data);

            if ($result) {
                $this->_changes = []; // reset
                $this->afterUpdate();
                $this->afterSave();
            } else {
                $this->addError('data-update', 'update a record is failure');
            }

            unset($data);
        }

        return $this;
    }

    /***********************************************************************************
     * delete operation
     ***********************************************************************************/

    /**
     * delete by model
     * @return int
     * @throws \InvalidArgumentException
     */
    public function delete(): int
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
     * @throws \InvalidArgumentException
     */
    public static function deleteByPk($pkValue): int
    {
        // only one
        $where = [static::pkField() => $pkValue];

        // many
        if (\is_array($pkValue)) {
            $where = [static::pkField(), 'IN', $pkValue];
        }

        return self::deleteBy($where);
    }

    /**
     * @param mixed $wheres
     * @return int
     * @throws \InvalidArgumentException
     */
    public static function deleteBy($wheres): int
    {
        return static::getDb()->delete(static::tableName(), $wheres);
    }

    /***********************************************************************************
     * transaction operation
     ***********************************************************************************/

    /**
     * @return bool
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function beginTransaction(): bool
    {
        return static::getDb()->beginTransaction();
    }

    /**
     * @return bool
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function commit(): bool
    {
        return static::getDb()->commit();
    }

    /**
     * @return bool
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function rollBack(): bool
    {
        return static::getDb()->rollBack();
    }

    /**
     * @return bool
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public static function inTransaction(): bool
    {
        return static::getDb()->inTransaction();
    }

    /***********************************************************************************
     * extra operation
     ***********************************************************************************/

    protected function beforeInsert(): bool
    {
        return true;
    }

    protected function afterInsert()
    {
    }

    protected function beforeUpdate(): bool
    {
        return true;
    }

    protected function afterUpdate()
    {
    }

    protected function beforeSave(): bool
    {
        return true;
    }

    protected function afterSave()
    {
    }

    protected function beforeDelete(): bool
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
    public function isNew(): bool
    {
        return !$this->get(static::pkField(), false);
    }

    /**
     * @param null|bool $value
     * @return bool
     */
    public function enableValidate($value = null): bool
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
        return static::pkField();
    }

    /**
     * @return mixed
     */
    public function pkValue()
    {
        return $this->get($this->pkField);
    }

    /**
     * Check whether the column's value is changed, the update.
     * @param string $column
     * @return bool
     */
    protected function valueIsChanged($column): bool
    {
        if ($this->isNew()) {
            return true;
        }

        return isset($this->_changes[$column]) && $this->get($column) !== $this->getChange($column);
    }

    /**
     * @return bool
     */
    public function isLoadFromDb(): bool
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
    public function getOldData(): array
    {
        return $this->_backup;
    }

    /**
     * @param array $data
     */
    public function setOldData(array $data)
    {
        $this->_backup = $data;
    }

    /**
     * @param string $column
     * @return mixed
     */
    public function getOld(string $column)
    {
        return $this->_backup[$column] ?? null;
    }

    /**
     * @return array
     */
    public function getChanges(): array
    {
        return $this->_changes;
    }

    /**
     * @param array $_changes
     */
    public function setChanges(array $_changes)
    {
        $this->_changes = $_changes;
    }

    /**
     * @param string $column
     * @return mixed
     */
    public function getChange(string $column)
    {
        return $this->_changes[$column] ?? null;
    }

    /**
     * @param string $column
     * @param mixed $value
     */
    public function setChange(string $column, $value)
    {
        if ($this->hasColumn($column)) {
            $this->_changes[$column] = $value;
        }
    }

    /**
     * @return string
     */
    public function getPkField(): string
    {
        return $this->pkField;
    }

    /**
     * getTableName
     * @return string
     */
    final public function getTableName(): string
    {
        return $this->tableName;
    }
}
