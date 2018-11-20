# simple activeRecord

[![License](https://img.shields.io/packagist/l/inhere/console.svg?style=flat-square)](LICENSE)
[![Php Version](https://img.shields.io/badge/php-%3E=7.0-brightgreen.svg?maxAge=2592000)](https://packagist.org/packages/php-comp/lite-activerecord)
[![Latest Stable Version](http://img.shields.io/packagist/v/php-comp/lite-activerecord.svg)](https://packagist.org/packages/php-comp/lite-activerecord)

一个简洁的php activeRecord库

功能：

- 简洁，使用简单
- 支持 `findByPk` `findOne` `findAll` `insert` `update` 等常用方法
- 内置数据验证检查，保存数据之前自动验证。（像 yii 的模型，验证基于 `inhere/php-validate`）

## 安装

- By composer require

```bash
composer require php-comp/lite-activerecord
```

- By composer.json

```json
{
    "require": {
        "php-comp/lite-activerecord": "~1.0"
    }
}
```

- Pull directly

```bash
git clone https://github.com/php-comp/lite-activeRecord.git
```

## 如何使用

- 引入类并继承

```php
use PhpComp\LiteActiveRecord\RecordModel

class User extends RecordModel
{
    // ...
    
    /**
     * 表名称(可选定义)
     * @return string
     */
    public static function tableName(): string
    {
        return 'user';
    }

    /**
     * 表的字段。
     *  - 必须定义，只有这里定义的字段才会被保存
     * @return array
     */
    public function columns(): array
    {
        return [
            // column => [type]

            'id' => ['int'],
            'name' => ['string'],
        ];    
    }
    
    
    /**
     * define attribute field translate list(可选定义)
     * @return array
     */
    public function translates(): array
    {
        return [
            // 'field' => 'translate',
            // e.g. 'name'=>'名称',
    }    
    
    /**
     * 数据验证规则(可选定义)
     * - 保存数据之前会自动验证
     * @return array
     */
    public function rules(): array
    {
        return [
            // ['body', 'string'],
            // ['id, createTime', 'int'],
    }
}
```

- 插入

```php
$user = User::load($data);
$user->insert();

if ($model->hasError()) {
    var_dump($model->firstError())
}

var_dump($user->id);
```

- 查找

```php
$user = User::findById(12);
$user = User::findOne(['name' => 'inhere']);

// 查找多个
$users = User::findAll([
    ['id', '>', 23], 
    'status' => [1, 2], // status IN (1,2)
    ['name', 'like', "%tom%"],
]);
```

- 更新

```php
$user->name = 'new name';
$user->update();
```

- 删除

```php
// 模型删除
$affected = $user->delete();

// 通过id删除
$affected = User::deleteByPk(23);
```

## Projects

- **github** https://github.com/inhere/php-simple-activeRecord.git
- **gitee** https://gitee.com/inhere/php-simple-activeRecord.git

## 参考

- https://github.com/illuminate/database
- https://github.com/ventoviro/windwalker-query

## License

MIT
