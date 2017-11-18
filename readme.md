## About

优雅的树形数据结构管理包,基于`Closure Table`模式设计.

**注意：2.1.0开始`getTree()方法返回的数据有所变化，加入一个`[]`包裹,以支持multi tree**
 > 经过在实际应用中的反馈,大部分场合都需要`parent_id`列,
 1.x版本中虽有相应的添加`parent_id`列方法,
 操作过于繁琐.
 
 > 基于实际应用和性能考虑,在2.0版本中数据表依赖`parent`列,这样既简单实用,又进一步减少了数据库查询，推荐使用.
 
## Features

- 优雅的树形数据设计模式
- 最少的数据库查询
- 一个Eloquent Trait简单操作
- 完善的树形结构操作方法
- 支持生成树形数据
- 支持多个根存在
- 支持节点/树修复
- 支持软删除
- ...

## 依赖

- php > 5.6.0
- laravel > 5.4.0
- mysql > 5.1.0

## 关于`Closure Table`

> Closure table is a simple and elegant way of storing and querying hierarchical data in any RDBMS. By hierarchical data we mean a set of data that has some parent – child relationship among them. We use the word ‘tree’ instead of hierarchies commonly. As an example we may take the relationships between geographic locations like ‘Countries’, ‘States/ Province’, ‘Districts/ Cities’ etc.

`Closure Table`将树中每个节点与其后代节点的关系都存储了下来,
这将需要一个存储相互关系的表`name_closure`.

例如一个菜单表`menus`:

|id|name|parent|
|:-:|:-:|:-:|
|1|首页|0|
|2|产品|1|
|3|终端产品|2|
|4|智能手机|3|

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

`ClosureTable`提供了大量方法操作树结构.

### 影响树结构的方法

```php
<?php

$menu = Menu::find(2);
  
// 将$menu作为根,return bool
$menu->makeRoot();
  
// 创建一个子级节点,return new model
$menu->createChild($attributes);
  
// 创建一个新的菜单，该菜单为根(parent=0),return model
$child = Menu::create($attributes);
  
// 将一个已存在的菜单添加到子级,$child可为模型实例、集合或id、包含id的数组,return bool
$menu->addChild($child);
$menu->addChild(12);
$menu->addChild('12');
$menu->addChild([3, 4, 5]);
  
// 移动到$parent的下级,后代也将随之移动,$parent可为模型实例或id,return bool
$menu->moveTo($parent);
$menu->moveTo(2); 
$menu->moveTo('2');
  
// 添加一个或多个同级节点,$siblings的后代也将随之移动,$siblings可为模型实例集合或id、包含id的数组,return bool
$menu->addSibling($siblings);
$menu->addSibling(2);
$menu->addSibling('2');
$menu->addSibling([2,3,4]);
  
// 新建一个同级节点,return new model
$menu->createSibling($attributes);
  
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
  
// 获取所有孤立节点,孤立节点指在`closureTable`里没有的记录
Menu::getIsolated();
  
Menu::isolated()->where('id', '>', 5)->get();
  
// 获取所有根
Menu::getRoots();
  
// 一个scope,同getRoots()
Menu::onlyRoot()->get();
```

* 以上`get...()`方法都包含一个query构造器,如`getDescendants()`对应有一个`queryDescendants`,

  这使得你可以在查询中加入条件查询或排序你可以这样使用
  
  `$menu->queryDescendants()->where('id', '>', 5)->orderBy('sort','desc')->get();`
  
  > `getRoot()`,`getParent()`,`getRoots()`,`getIsolated()`4个方法没有query构造器

* 如果你想获取只包含单个或多个列的结果可以在`get...()`方法里传入参数,如:

  `$menu->getAncestors(['id','name']);`

### 生成树形数据的方法

提供多种方法生成树形数据,可从任意节点生成树
```php
<?php

$menu = Menu::find(3);
  
// 从当前节点生成树,return tree
$menu->getTree();
  
// 当前节点作为根生成树,以sort字段排序,return tree
$menu->getTree(['sortColumn', 'desc']);
  
// 同上,return tree
$menu->getDescendantsAndSelf()->toTree();
  
// 获取到以所有children为根的multi tree
$menu->getDescendants()->toTree();
  
// 从根节点生成树,return tree
$menu->getRoot()->getTree();

//旁树,不包含自己和下级,return tree
$menu->getBesideTree();
```

生成的树如下:
```php

[
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
]
```

* 生成的树`children`键默认为`children`,如果你想自定义可以作为第2个参数传入,如:

  `$menu->getTree(['sortColumn', 'desc'], 'son');`

  如果你想获取只包含单个或多个列的结果可以作为第3个参数传入,如:

  `$menu->getTree(['sortColumn', 'desc'], 'son', ['id', 'name']);`

* 你的表里可能包含多棵树,如果你想一一获取他们可以这样做:

    ```php
    <?php
    
    $multiTree = Menu::all()->toTree();
  
    var_dump($multiTree);
  
    ```

### 判断方法

```php
<?php

$menu = Menu::find(3);
  
// 是否根
$menu->isRoot();
  
// 是否叶子节点
$menu->isLeaf();
 
// 是否孤立节点
$menu->isIsolated();
  
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

### 删除操作

`ClosureTable`监听了模型的`deleting`事件

```php
$menu->delete();
```

删除(包括软删除)一条记录,这个操作将解除自身的所有关联,
**并且其`children`会成为根,这意味着`children`成立了自己的树.**

**请勿使用以下方法来删除模型**

`Menu::destroy(1);`

`Menu::where('id', 1)->delete()`

因为这些操作不会触发`deleting`事件

> 软删除的恢复,同样恢复它在树中的位置.

### 结构维护

```php
<?php

// 清理冗余的关联信息
Menu::deleteRedundancies();
  
$menu = Menu::find(20);
  
// 修复此节点的关联
$menu->perfectNode();
  
// 修复树关联,注意:这个操作将追朔到到根节点然后从根遍历整颗树调用perfectNode(),如果你的树很庞大将耗费大量资源,请慎用.
// 替代方法是使用队列对每个节点perfectNode()
$menu->perfectTree();

```
 > 此包还监听了`created`,`updating`,`restored`事件,
 这意味着如果你在修改`parent`同样能改变树结构.
 对于`Model::create()`方法，如果指定了`parent`效果同`$menu->createChild()`.
 
## 安装

```bash
$ composer requrie jiaxincui/closure-table
```

- `data`表必要列`id`,`parent`,

- `closure`表必要列`ancestor`,`descendant`,`distance`

示例:

```php
<?php

Schema::create('menus', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedTinyInteger('parent')->default(0);
        });

Schema::create('menu_closure', function (Blueprint $table) {
            $table->unsignedInteger('ancestor');
            $table->unsignedInteger('descendant');
            $table->unsignedTinyInteger('distance');
            $table->primary(['ancestor', 'descendant']);
        });
```

1. 在`model`里use trait `Jiaxincui\ClosureTable\Traits\ClosureTable`.

2. 如果你想自定义关联表名和字段，可在`model`里定义以下属性:`$closureTable`,`$ancestorColumn`,`$descendantColumn`,`$distanceColumn`.

3. 如果你想自定义`parent`字段,在`model`里定义属性`$parentColumn`.
  
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
      
    // parent列名,默认'parent'
    protected $parentColumn = 'parent';
    
}
```

接下来,你就可以自由的使用`ClosureTable`带来的所有功能了.

## License

[MIT](https://github.com/jiaxincui/closure-table/blob/master/LICENSE.md) © [JiaxinCui](https://github.com/jiaxincui)

