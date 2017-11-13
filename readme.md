## About

优雅的树形数据结构管理包,基于`Closure Table`模式设计.

## Features

- 优雅的树形数据设计模式
- 数据和结构分表,操作数据不影响结构
- 一个Eloquent Trait操作简单
- 无需修改表,兼容旧数据
- 完善的树操作方法
- 支持生成树形数据
- 支持多棵树并存(多个根)
- 支持节点/树修复
- 支持软删除

## 关于`Closure Table`

> Closure table is a simple and elegant way of storing and querying hierarchical data in any RDBMS. By hierarchical data we mean a set of data that has some parent – child relationship among them. We use the word ‘tree’ instead of hierarchies commonly. As an example we may take the relationships between geographic locations like ‘Countries’, ‘States/ Province’, ‘Districts/ Cities’ etc.

`Closure Table`将树中每个节点与其后代节点的关系都存储了下来,
这将需要一个存储相互关系的表`name_closure`.

部门表:

|id|name
|:-:|:-:|
|1|总经理|
|2|副总经理|
|3|行政主管|
|4|文秘|

一个基本的`closure`表包含`ancestor`,`descendant`,`distance`3个字段,如:

|ancestor|descendant|distance|
|:-:|:-:|:-:|
|1|1|0|
|1|2|1|
|1|3|2|
|1|4|3|
|2|2|0|
|2|3|1|
|2|4|2|
|3|3|0|
|3|4|1|
|4|4|0|

这个表记录了每个部门之间的关系,并且还记录了一条自身的关联.

## 使用

`ClosureTable`提供了大量方法操作树.

### 影响树结构的方法
```php
<?php

$menu = Menu::find(10);
  
// 将$menu作为根,return bool
$menu->makeRoot();
  
// 创建一个子级节点,return new model
$menu->createChild($attributes);
  
// 创建一个新的菜单，此时该菜单无任何关联,return model
$child = Menu::create($attributes);
  
// 将一个已存在的菜单添加到子级,$child可为模型实例、模型实例集合或id、包含id的数组,return bool
$menu->addChild($child);
$menu->addChild(12);
$menu->addChild('12');
$menu->addChild([3, 4, 5]);
  
// 移动到$parent的下级,后代也将随之移动,$parent可为模型实例或id,return bool
$menu->moveTo($parent);
$menu->moveTo(2); 
$menu->moveTo('2');
  
// 同moveTo()
$menu->addTo($parent);
  
// 添加一个或多个同级节点,$siblings的后代也将随之移动,$siblings可为模型实例集合或id、包含id的数组,return bool
$menu->addSibling($siblings);
$menu->addSibling(2);
$menu->addSibling('2');
$menu->addSibling([2,3,4]);
  
// 新建一个同级节点,return new model
$menu->createSibling($attributes);
  
// 建立一个自身的关联,return bool
$menu->attachSelf();
  
// 解除自身的所有关联,并且解除后代的所有关联(这个操作不保留子树，将使自己和所有后代都成孤立状态),return bool
$menu->detachSelf();
```

### 获取数据的方法

```php
<?php
$menu = Menu::find(3);
  
// 获取所有后代,return model collection
$menu->getDescendants();
  
// 获取所有后代,包括自己,return model collection
$menu->getDescendantsAndSelf();
 
 // 获取所有祖先,return model collection
$menu->getAncestors();
  
// 获取所有祖先,包括自己,return model collection
$menu->getAncestorsAndSelf();
  
// 获取所有儿女(直接下级),return model collection
$menu->getChildren();
  
// 获取父辈(直接上级),return model
$menu->getParent();
  
// 获取祖先(根),return model
$menu->getRoot();

// 获取所有兄弟姐妹,return model collection
$menu->getSiblings();
  
//获取所有兄弟姐妹包括自己,return model collection
$menu->getSiblingsAndSelf();
  
// 获取所有孤立节点
Menu::getIsolated();
  
Menu::isolated()->where('id', '>', 5)->get();
  
// 获取所有根
Menu::getRoots();
```

* 以上`get...()`方法都包含一个query构造器,如`getDescendants()`对应有一个`queryDescendants`,这使得你可以在查询中加入条件查询或排序
你可以这样使用`$menu->queryDescendants()->where('id', '>', 5)->orderBy('sort','desc')->get();`
  > `getRoot()`,`getParent()`两个方法不没有query构造器

* 如果你想获取只包含单个或多个列的结果可以在`get...()`方法里传入参数,如:`$menu->getAncestors(['id','name']);`

* 由于数据库不需要`parent_id`列,如果你想在结果中显示包含此列的内容可以在构造器后加入`withParent()`,
如:`$menu->queryDescendantsAndSelf()->withParent()->get()`.
默认列名为`parent`,如果你想自定义这个列名在`model`里定义`protected $parentColunm = 'parent_id'`

### 生成树形数据

提供多种方法生成树形数据,可从任意节点生成树
```php
<?php

$menu = Menu::find(3);
  
// 从当前节点生成树,return tree
$menu->getTree();
  
// 当前节点作为根生成树,以sort字段排序,return tree
$menu->getTree(['sortColumn', 'desc']);
  
// 从根节点生成树,return tree
$menu->getRoot()->getTree();

//旁树,不包含自己和下级,return tree
$menu->getBesideTree();
```

生成的树如下:
```php

[
    'id' => 3,
    'name' => 'node3',
    'children' => [
        [
            'id' => 4,
            'name' => 'node4'
        ],
        [
            'id' => 5,
            'name' => 'node5'
            'children' => [
                [
                    'id' => 6,
                    'name' => 'node6'
                ]
            ]
        ]
    ]
]


```
* 生成的树的`children`键默认为`children`,如果你想自定义可以作为第2个参数传入,如:
`$menu->getTree(['sortColumn', 'desc'], 'son');`
如果你想获取只包含单个或多个列的结果可以作为第3个参数传入,如:
`$menu->getTree(['sortColumn', 'desc'], 'son', ['id', 'name']);`

* 你的表里可能包含多棵树,如果你想一一获取他们可以这样做:
    ```php
    <?php
    
    $multiTree = [];
    $roots = Menu::getRoots();
    foreach ($roots as $root) {
        $multiTree[] = $root->getTree();
    }
    $data = $mutiTree;
    
    ```

### 判断

```php
<?php

$menu = Menu::find(3);
  
// 是否根
$menu->isRoot();
  
// 是否叶子节点
$menu->isLeaf();
 
// 是否孤立节点
$menu->isIsolated();
  
// 是否有上级
$menu->hasAncestors();
  
// 是否有下级
$menu->hasDescendants();
  
// 是否有孩子(直接下级)
$menu->hasChildren();
  
// 是否有直接上级
$menu->hasParent();
  
// 是否$descendant的上级
$menu->isAncestorOf($descendant);
  
// 是否$ancestor的下级
$menu->isDescendantOf($ancestor);
  
// 是否$parent的直接下级
$menu->isChildOf($parent);
  
// 是否$child的直接上级
$menu->isParentOf($child);
  
// 是否$sibling的同级(同一个上级)
$menu->isSiblingOf($sibling);
  
// 如果$beside不是自己也不是自己的后代返回true
$menu->isBesideOf($beside);
```

### 数据维护

```php
<?php

// 清理冗余的关联信息
Menu::deleteRedundancies();
  
$menu = Menu::find(20);
  
// 修复此节点的关联
$menu->perfectNode();
  
// 修复树关联,注意:这将循环整颗树调用perfectNode(),如果你的树很庞大将耗费大量资源,请慎用
$menu->perfectTree();

```

## 安装

```bash
$ composer requrie jiaxincui/closure-table
```

建立树需要新建一个`closure`表如:`menu_closure`

```php
<?php

Schema::create('menu_closure', function (Blueprint $table) {
            $table->unsignedInteger('ancestor');
            $table->unsignedInteger('descendant');
            $table->unsignedTinyInteger('distance');
            $table->primary(['ancestor', 'descendant']);
        });
```

1. 在`model`里使用`Jiaxincui\ClosureTable\Traits\ClosureTable`Trait.

2. 如果你想自定义表名和字段，可在`model`里定义以下属性:`$closureTable`,`$ancestorColumn`,`$descendantColumn`,`$distanceColumn`.

3. 如果你想自定义生成的树形数据里`parent`字段,在`model`里定义属性`$parentColumn`.
  
  如下示例:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Jiaxincui\ClosureTable\Traits\ClosureTable;

class Menu extends Model
{
    use ClosureTable;
 
    // 关联表名，默认'Model类名+_closure',如'menu_closure'
    protected $closureTable = 'menu_closure';
      
    // ancestor列名,默认'ancestor'
    protected $ancestorColumn = 'ancestor';
      
    // descendant列名,默认'descendant'
    protected $descendantColumn = 'descendant';
      
    // distance列名,默认'distance'
    protected $distanceColumn = 'distance';
      
    // parent列名,默认'parent',此列是计算生成,不在数据库存储
    protected $parentColumn = 'parent';
    
}
```

接下来,你就可以自由的使用`ClosureTable`带来的所有功能了.

## License

[MIT](https://github.com/jiaxincui/closure-table/blob/master/LICENSE.md) © [JiaxinCui](https://github.com/jiaxincui)

