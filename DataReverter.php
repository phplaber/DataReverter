<?php
// 数据回滚组件

class DataReverter extends CApplicationComponent
{
    // 默认DB组件名称
    public $defaultDb = 'db';
    // 默认缓存生命周期
    public $defaultExpire = 10800;
    private $_cache = null;
    private $_filecache = null;
    private $_table = '';
    private $_data  = array();
    private $_where = '';
    private $_pk = '';
    private $_fieldList = array();
    private $_range = array();

    public function init()
    {
        parent::init();
        if ($this->_cache === null)
            $this->_cache = Yii::app()->memcache;

        if ($this->_filecache === null) {
            if (Yii::app()->hasComponent('cache') && ($cache = Yii::app()->getCache()) instanceof CFileCache) {
                $this->_filecache = $cache;
            } else {
                $this->_filecache = new CFileCache;
                $this->_filecache->init();
            }
        }
    }

    public function setTable($table = '')
    {
        if (empty($table))
            throw new CException('The table cannot be empty');

        $this->_table = $table;

        return $this;
    }

    public function setWhere($where = '')
    {
        if (empty($where))
            throw new CException('The condition cannot be empty');

        $this->_where = $where;

        return $this;
    }

    public function setField($fieldList = array())
    {
        if (empty($fieldList))
            throw new CException('The fields cannot be empty');

        // 增加主键字段
        !in_array($this->_pk, $fieldList) && array_unshift($fieldList, $this->_pk);
        $this->_fieldList = $fieldList;

        return $this;
    }

    public function setPk()
    {
        // 获取主键
        $queryKeySql = <<<QUERY
    SHOW KEYS FROM {$this->_table} WHERE Key_name = 'PRIMARY';
QUERY;
        $row = Yii::app()->{$this->defaultDb}->createCommand($queryKeySql)->queryRow();
        $this->_pk = $row['Column_name'];

        return $this;
    }

    public function setPkRange($range = '')
    {
        if (empty($range) || strpos($range, '-') === false)
            throw new CException('The pk range cannot be empty or invalid format');

        $this->_range = explode('-', $range);

        return $this;
    }

    public function query()
    {
        $fields = implode(',', $this->_fieldList);

        $sql = <<<QUERY
    select {$fields} from {$this->_table} where {$this->_where};
QUERY;
        $ret = Yii::app()->{$this->defaultDb}->createCommand($sql)->queryAll();
        if ($ret) {
            $this->_data = $ret;
        }

        return $this;
    }

    public function store()
    {
        if (!$this->_cache->set($this->getKey(), $this->_data, $this->defaultExpire))
            throw new CException('Memcache must be required');

        // hack: use file for persistent cache
        $this->_filecache->set($this->getKey(), $this->_data, 604800);
    }

    public function revert()
    {
        $transaction = Yii::app()->{$this->defaultDb}->beginTransaction();
        $isException = false;

        $pk = $this->_pk;
        if (!empty($this->_where)) {
            $data = $this->_cache->get($this->getKey());
            !$data && $data = $this->_filecache->get($this->getKey());
            if ($data === false)
                throw new CException('Revert failed');

            foreach ($data as $item) {
                $update = "update {$this->_table} set ";
                foreach ($item as $field => $value) {
                    if ($field == $pk) continue;
                    $update .= $field . '=' . (is_int($value) ? $value : "'{$value}'") . ',';
                }
                $update = rtrim($update, ',');
                $update .= " where {$pk} = $item[$pk]; ";

                try {
                    Yii::app()->{$this->defaultDb}->createCommand($update)->execute();
                } catch (CException $e) {
                    $isException = true;
                }
            }
        } elseif (!empty($this->_range)) {
            // 根据主键ID范围去删除
            $start = intval($this->_range[0]);
            $end = intval($this->_range[1]);
            $delete = "delete from {$this->_table} where {$pk} >= {$start} and {$pk} <= {$end}; ";

            try {
                Yii::app()->{$this->defaultDb}->createCommand($delete)->execute();
            } catch (CException $e) {
                $isException = true;
            }
        } else {}

        // 提交或回滚
        !$isException ? $transaction->commit() : $transaction->rollback();
    }

    protected function getKey()
    {
        return md5(__CLASS__ . $this->defaultDb . $this->_table . $this->_where);
    }
}