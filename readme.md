## About

优雅的树形数据结构管理包,基于`Closure Table`模式设计.

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

- php > 8.0
- laravel 9.0 - 11.0
- mysql > 5.1.0

**3.0 版本进行了重大更新，加入了字符串ID (如uuid等) 的支持、使用了更安全的SQL语、更规范的方法签名等**

**3.0 版本更改了根节点的 `parent` 列，之前默认使用 `0`, 3.0版本默认使用 `null`， 如果你准备从3.0以下版本升级，需要更新你的数据库，使 `parent` 列默认为 'null'**

**`laravel < 9.0` ，请使用 `2.1.x` 版本**

## 关于 `Closure Table`

> Closure table is a simple and elegant way of storing and querying hierarchical data in any RDBMS. By hierarchical data we mean a set of data that has some parent – child relationship among them. We use the word ‘tree’ instead of hierarchies commonly. As an example we may take the relationships between geographic locations like ‘Countries’, ‘States/ Province’, ‘Districts/ Cities’ etc.

`Closure Table` 将树中每个节点与其后代节点的关系存储在数据库中,
这将需要一个存储节点关系的表 `xxx_closure`.

例如一个菜单表 `menus`:

|id|name| parent |
|:-:|:-:|:----:|
|1|A|  null |
|2|AA|   1  |
|3|AB|   1  |
|4|AAA|   2  |
|5|ABA|   3  |
|6|ABAA|   5  |

一个基本的 `closure` 表包含 `ancestor` ,`descendant` , `distance` 3个字段,如:

|ancestor|descendant|distance|
|:-:|:-:|:-:|
|1|1|0|
|1|2|1|
|1|3|1|
|1|4|2|
|1|5|2|
|1|6|3|
|2|2|0|
|2|4|1|
|3|3|0|
|3|5|1|
|3|6|2|
|4|4|0|
|5|5|0|
|5|6|1|
|6|6|0|

这个表记录了每个节点之间的关系,并且还记录了一条自身的关联,如：`1,1,0`.

## 使用

`ClosureTable` 提供了大量方法操作树结构.

### 影响树结构的方法

```php
<?php

$menu = Menu::find(2);
  
// 将$menu作为根,return bool
$menu->makeRoot();
  
// 创建一个子级节点,return new model
$menu->createChild($attributes);
  
// 创建一个新的节点，该节点为根（如果未指定 parent 列）
$child = Menu::create($attributes);
  
// 将一个已存在的节点添加到子级,$child参数可以是模型实例/id,return void
$menu->addChild($child);
$menu->addChild(12);
  
// 移动到$parent的下级,它的所有下级节点也将随之移动,$parent参数可以是模型实例/id,return void
$menu->moveTo($parent);
$menu->moveTo(2); 
  
// 添加一个或多个同级节点,$sibling的所有下级节点也将随之移动,$siblings可以是模型实例/id,return void
$menu->addSibling($sibling);
$menu->addSibling(2);
  
// 新建一个同级节点,return Model
$menu->createSibling($attributes);
  
```
 > 它监听了 `created`,`updating`,`restored` 事件,如果你使用了 `create()`,或对实例使用了 `update(), restore()` 它将自动维护树结构.
 这意味着如果你正在修改 `parent` 列,它也会自动维护树结构.

 
### 获取数据的方法

```php
<?php
$menu = Menu::find(3);
  
// 获取所有后代,返回列表
$menu->getDescendants();
  
// 获取所有后代,包括自己,返回列表
$menu->getDescendantsAndSelf();
 
 // 获取所有祖先,返回列表
$menu->getAncestors();
  
// 获取所有祖先,包括自己,返回列表
$menu->getAncestorsAndSelf();
  
// 获取所有儿女(直接下级),返回列表
$menu->getChildren();

// 获取上级节点,返回单个实例/null
$menu->getParent();
  
// 获取根（根节点返回本身）,返回单个实例/null
$menu->getRoot();

// 获取所有同级节点, 返回列表
$menu->getSiblings();
  
//获取所有同级节点并包括本身,返回列表
$menu->getSiblingsAndSelf();

// 获取所有孤立节点（孤立节点指在没有在 closureTable 表里维护的记录）
Menu::onlyIsolated()->get();
  
// 获取所有根, 返回列表
Menu::onlyRoot()->get();
```

* 以上 `getXxx()` 方法都包含一个 query 构造器,如 `getDescendants() `对应有一个 `queryDescendants()`,

  这使得你可以在查询中加入更多条件如：`orderBy`,
  
  你可以这样使用
  
  `$menu->queryDescendants()->where('id', '>', 5)->orderBy('sort','desc')->get();`
  
  > 注意 `getRoot()`,`getParent()` 2个方法没有 query 构造器

* 如果你想获取只包含单个或多个字段的结果可以在 `getXxx()` 方法里传入参数,如:

  `$menu->getAncestors(['id','name']);`


### 生成树形数据的方法

提供多种方法生成树形数据,可从任意节点生成树

```php
<?php

$menu = Menu::find(3);
  
// 从当前节点生成树,return tree array
$menu->getTree();
  
// 当前节点作为根生成树,以sort字段排序,return array tree
$menu->getTree(['sortColumn', 'desc']);
  
// 同上,return tree array
$menu->getDescendantsAndSelf()->toTree();
  
// 获取到以所有children为根的multi tree array
$menu->getDescendants()->toTree();
  
// 从根节点生成树,return tree array
$menu->getRoot()->getTree();

//旁树,不包含自己和下级,return tree array
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

* 生成的树 `children` 键默认为 `children` ,如果你想自定义可以作为第2个参数传入,如:

  `$menu->getTree(['sortColumn', 'desc'], 'son');`
  
  其中 `sortColumn` 是排序的列， `desc` 是排序方法

* 如果你想获取只包含单个或多个列的结果可以作为第3个参数传入,如:

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
  
// 是否某的上级
$menu->isAncestorOf($descendant);
  
// 是否某的下级
$menu->isDescendantOf($ancestor);
  
// 是否某的直接下级
$menu->isChildOf($parent);
  
// 是否某的直接上级
$menu->isParentOf($child);
  
// 是否某的同级(同一个上级)
$menu->isSiblingOf($sibling);
  
// 如果$beside不是自己，也不是自己的后代返回true
$menu->isBesideOf($beside);
```

### 删除操作

`ClosureTable` 监听了模型的 `deleting` 事件

```php
$menu->delete();
```

删除(包括软删除)一条记录,这个操作将解除自身的所有关联,

**并且其所有 `children` 会成为根（parent = 0）,这意味着所有的 `children` 成立了自己的树.**

**请勿使用以下方法来删除模型**

`Menu::destroy(1);`

`Menu::where('id', 1)->delete()`

因为这些操作不会触发`deleting`事件

> 支持软删除,软删除的 `restore()`, 会根据它的 `parent` 列的记录恢复至相应的位置，你不需要去关心 `closure` 表里的记录，它已经帮你做了.

### 结构维护

```php
<?php

// 清理冗余的关联信息
Menu::deleteRedundancies();
  
$menu = Menu::find(20);
  
// 修复此节点的关联, 它将重新建立 `closure` 表里的记录
$menu->perfectNode();
  
// 修复树关联,注意:这个操作将追朔到到根节点然后从根遍历整颗树调用perfectNode(),如果你的树很庞大将耗费大量资源,请慎用.
// 解决方案是使用队列对每个节点使用 perfectNode()
$menu->perfectTree();

```

## 安装

```bash
$ composer require jiaxincui/closure-table
```

- 你的模型中必要的列 `id`,`parent`, 当然你也可以在模型中自定义这些列（见后面章节）

- `closure` 表必需的列 `ancestor`,`descendant`,`distance`， 如有必要你也可以自定义（见后面章节）

示例:

```php
<?php

Schema::create('menus', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            // parent 列允许为 null，因为根节点的 parent 为 null，
            // ！！！根节点的 parent 一定为 null，请勿自定义为其它值
            $table->unsignedInteger('parent')->nullable();
        });

Schema::create('menu_closure', function (Blueprint $table) {
            $table->unsignedInteger('ancestor');
            $table->unsignedInteger('descendant');
            $table->unsignedTinyInteger('distance');
            $table->primary(['ancestor', 'descendant']);
        });
```

1. 在`model`里引入 trait `Jiaxincui\ClosureTable\Traits\ClosureTable`.

2. (非必需)如果你想自定义关联表名和字段，可在`model`里定义以下属性:`$closureTable`,`$ancestorColumn`,`$descendantColumn`,`$distanceColumn`.

3. (非必需)如果你想自定义`parent`字段,在`model`里定义属性`$parentColumn`.
  
  如下示例:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Jiaxincui\ClosureTable\Traits\ClosureTable;

class Menu extends Model
{
    // (必要)引入ClosureTable.
    use ClosureTable;
 
    // (非必要)关联表名，默认'Model类名+_closure',如'menu_closure'
    protected $closureTable = 'menu_closure';
      
    // (非必要)ancestor列名,默认'ancestor'
    protected $ancestorColumn = 'ancestor';
      
    // (非必要)descendant列名,默认'descendant'
    protected $descendantColumn = 'descendant';
      
    // (非必要)distance列名,默认'distance'
    protected $distanceColumn = 'distance';
      
    // (非必要)parent列名,默认'parent', 要求此列可以为`null`
    protected $parentColumn = 'parent';
    
}
```

接下来,你就可以自由的使用`ClosureTable`带来的所有功能了.

## License

[MIT](https://github.com/jiaxincui/closure-table/blob/master/LICENSE.md) © [JiaxinCui](https://github.com/jiaxincui)

