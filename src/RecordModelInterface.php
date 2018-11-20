<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2018/5/9 0009
 * Time: 00:06
 */

namespace PhpComp\LiteActiveRecord;

/**
 * Interface RecordModelInterface
 * @package PhpComp\LiteActiveRecord
 */
interface RecordModelInterface
{
    const SCENE_DEFAULT = 'default';
    const SCENE_CREATE = 'create';
    const SCENE_UPDATE = 'update';
    const SCENE_DELETE = 'delete';
    const SCENE_SEARCH = 'search';
}
