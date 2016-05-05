# DataReverter
一个用于回滚已变更DB数据的Yii的组件

#### 目的
回滚那些需要执行完SQL后人肉去检查或执行SQL之后才能知道是否正确的数据。

#### 目标
1.  Yii风格组件形式，继承CApplicationComponent类；
2.  灵活，支持多张表&多个字段数据回滚；
3.  OOP风格，封装良好，便于使用；
4.  健壮可靠；
5.  提供Web配置界面。

#### 优缺点

##### 优点
1.  更新或删除操作完全基于主键；
2.  严格检测必填字段；
3.  回滚时使用事务，确保数据一致性；
4.  提供Web配置界面，操作方便；
5.  部署简单。

##### 缺点
1.  只支持单表数据回滚；
2.  依赖memcache，如果缓存数据丢失，则数据不可回滚。

#### 组成
1.  UI - 提供Web界面用于配置（回滚方式(更新or删除) + 更新：表名，查询条件，字段；删除：表名，主键范围）；
2.  Ajax - 异步请求；
3.  Controller - 接受请求，调用组件方法处理请求；
4.  Component - 封装业务逻辑；
5.  Config - 注册和初始化组件；
6.  Memcache - 储存原始数据。

#### 控制器代码片段

*  储存数据Action

```php
<?php

public function actionStore()
{
    $response = $this->_packResponse(405, 'Method not allowed');
    if (Yii::app()->request->getIsAjaxRequest()) {
        $table = Yii::app()->request->getPost('table');
        $fieldList = array_filter(explode(',', Yii::app()->request->getPost('field')));
        $where = Yii::app()->request->getPost('where');
        try {
            Yii::app()->revert
                ->setTable($table)
                ->setWhere($where)
                ->setPk()
                ->setField($fieldList)
                ->query()
                ->store();
            $response = $this->_packResponse();
        } catch (CException $e) {
            $response = $this->_packResponse(500, $e->getMessage());
        }
    }

    @header('Content-type: application/json');
    exit(json_encode($response));
}
```

*  回滚数据Action

```php
<?php

public function actionRevert()
    {
        $response = $this->_packResponse(405, 'Method not allowed');
        if (Yii::app()->request->getIsAjaxRequest()) {
            $table = Yii::app()->request->getPost('table');
            $type = Yii::app()->request->getPost('type');
            $where = Yii::app()->request->getPost('where');
            try {
                if ($type == 'update') {
                    Yii::app()->revert
                        ->setTable($table)
                        ->setWhere($where)
                        ->setPk()
                        ->revert();
                } elseif ($type == 'delete') {
                    Yii::app()->revert
                        ->setTable($table)
                        ->setPk()
                        ->setPkRange($where)
                        ->revert();
                } else {
                    // todo
                }
                $response = $this->_packResponse();
            } catch (CException $e) {
                $response = $this->_packResponse(500, $e->getMessage());
            }
        }

        @header('Content-type: application/json');
        exit(json_encode($response));
    }
```

*  封装响应数据方法

```php
<?php

private function _packResponse($code = 200, $msg = 'OK')
    {
        return array(
            'code' => $code,
            'message' => $msg
        );
    }
```

#### 部署
部署Yii组件的方法，在配置文件里引入一个组件即可。

```php
<?php
// ...
'components' => array(
        'revert' => array(
            'class' => 'DataReverter',
        ),
    ),
// ...
```

#### UI
![alt DataReverter](https://raw.githubusercontent.com/phplaber/DataReverter/master/DataReverter.png)
