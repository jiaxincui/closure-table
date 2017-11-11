## About

优雅的树形数据结构管理包,基于`Closure Table`模式设计.

## Features

- 优雅的树形数据设计模式
- 数据和结构分表,操作数据不影响结构
- 一个Eloquent Trait操作简单
- 直接在需要生成树的Model上使用无需考虑旧数据兼容性
- 完善的树操作方法
- 支持生成树形数据
- 支持节点/树修复
- 支持软删除

## 关于`Closure Table`

> Closure table is a simple and elegant way of storing and querying hierarchical data in any RDBMS. By hierarchical data we mean a set of data that has some parent – child relationship among them. We use the word ‘tree’ instead of hierarchies commonly. As an example we may take the relationships between geographic locations like ‘Countries’, ‘States/ Province’, ‘Districts/ Cities’ etc.

`Closure Table`将树中每个节点与其后代节点的关系都存储了下来,
这将需要一个存储相互关系的表`some_closure`.

部门表:

|id|name|
|---|---|
|1|总经理|
|2|副总经理|
|3|行政主管|
|4|文秘|

一个基本的`closure`表包含`ancestor`,`descendant`,`distance`3个字段,如:

|ancestor|descendant|distance|
|--------|----------|--------|
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

这个表描述了每个部门之间的关系,并且还记录了一条自身的关联信息.

## 使用

`ClosureTable`提供了大量方法操作树.

### 影响树结构的方法
```php
<?php

$menu = Menu::find(10);

$menu->makeRoot(); // 作为根,return bool

$menu->createChild($attributes); // 创建一个下级菜单,return bool

$menu = Menu::create($attributes); // 创建一个新的菜单,return model

$menu->addChild($child); // 添加一个child,return bool

$menu->addChild(12); // 将id为3的$menu添加到下级,return bool

$menu->addChild('12'); //同上,return bool

$menu->addChild([3, 4, 5]); // 批量添加,return bool

$menu->moveTo($parent); // 移动到$parent的子代,后代也将随之移动,return bool

$menu->moveTo(2); // 传入id,效果同上,return bool

$menu->moveTo('2'); // 传入id,效果同上,return bool

$menu->addTo($parent); // 同moveTo()

$menu->addSibling($siblings); // 添加一个或多个同级节点,后代也将随之移动

$menu->addSibling(2); // 传入id,效果同上

$menu->createSibling($attributes); // 新建一个同级节点

```

### 获取数据的方法

```php
<?php
$menu = Menu::find(3);

$menu->getDescendants(); // 获取所有后代

$menu->queryDescendants()->where('id', '>', 5)->orderBy('sort','desc')->get(); // 获取所有id大于5的后代并排序

$menu->getDescendantsAndSelf(); // 获取所有后代,包括自身

$menu->queryDescendantsAndSelf()->where('id', '>', 5)->orderBy('sort','desc')->get(); // 获取包括自身id大于5的后代并排序

$menu->getAncestors(); // 获取所有祖先

$menu->queryAncestors()->orderBy('distance','desc')->get(); // 获取所有祖先,并以层级排序

$menu->getAncestorsAndSelf(); // 获取所有祖先,包括自身

$menu->queryAncestorsAndSelf()->orderBy('distance','desc')->get(); // 获取包括自身的所有祖先,并以层级排序

$menu->getChildren(); // 获取所有儿女(直接下级)

$menu->queryChildren()->orderBy('sort', 'desc')->get(); // 获取所有孩子(直接下级),并排序

$menu->getParent(); // 获取父辈(直接上级)

$menu->getRoot(); // 获取祖先(根)

$menu->getSiblings(); // 获取所有兄弟姐妹

$menu->querySiblings()->orderBy('sort', 'desc')->get(); // 获取所有兄弟姐妹并排序

$menu->getSiblingsAndSelf(); // 获取所有兄弟姐妹包括自身

$menu->querySiblingsAndSelf()->orderBy('sort', 'desc')->get(); //获取所有兄弟姐妹包括自身并排序

$menu->getDescendantsWithParent(); // 获取后代，在结果集添加parent字段

$menu->queryDescendantsWithParent()->orderBy('sort', 'desc')->get(); // 获取后代，在结果集添加parent字段并排序

Menu::getIsolated(); // 获取所有孤立节点

Menu::scopeIsolated()->where('id', '>', 5)->get(); // 获取id大于5的孤立节点

```

### 生成树形数据

提供多种方法生成树形数据,可从任意节点生成树
```php
<?php

$menu = Menu::find(3);

$menu->getTree(); // 从当前节点生成树

$menu->getTree('sort', 'desc'); // 当前节点作为根生成树,以sort排序

$menu->queryDescendantsWithParent()->orderBy('sort', 'desc')->get()->toTree(); //效果同上

$menu->getRoot()->getTree(); // 从根节点生成树

```

### 判断的方法

```php
<?php

$menu = Menu::find(3);

$menu->isRoot(); // 是否根

$menu->isLeaf(); // 是否叶子节点

$menu->isIsolated(); // 是否孤立节点

$menu->hasAncestors(); // 是否有上级

$menu->hasDescendants(); // 是否有下级

$menu->hasChildren(); // 是否有直接下级

$menu->hasParent(); // 是否有直接上级

$menu->isAncestorOf($descendant); // 是否$descendant的上级

$menu->isDescendantOf($ancestor); // 是否$ancestor的下级

$menu->isChildOf($parent); // 是否$parent的直接下级

$menu->isParentOf($child); // 是否$child的直接上级

$menu->isSibling($sibling); // 是否$sibling的同级(同一个上级)

```

### 数据维护

```php
<?php

Menu::deleteRedundancies(); // 清理冗余的关联信息

$menu = Menu::find(20);

$menu->perfectNode(); // 修复节点关联

$menu->perfectTree(); // 修复树关联,注意:这将循环修复树的每个节点,如果你的树很庞大将非常耗费资源,请慎用

```

## 安装

```bash
$ composer requrie jiaxincui/closure-table
```

建立`closure`表如:`menu_closure`

```php
<?php

Schema::create('menu_closure', function (Blueprint $table) {
            $table->unsignedInteger('ancestor');
            $table->unsignedInteger('descendant');
            $table->unsignedTinyInteger('distance');
            $table->primary(['ancestor', 'descendant']);
        });
```

- 在`model`里使用`Jiaxincui\ClosureTable\Traits\ClosureTable`Trait.

- 如果你想自定义表名和字段，可在`model`里定义以下属性:`$closureTable`,`$ancestorColumn`,`$descendantColumn`,`$distanceColumn`.

- 如果你想自定义生成的树形数据里`parent`字段,在`model`里定义属性`$parentColumn`.

如下示例:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Jiaxincui\ClosureTable\Traits\ClosureTable;

class Menu extends Model
{
    use ClosureTable;
    /*
     * 关联表名，默认Model类名+_closure,如menu_closure
     */
    protected $closureTable = 'menu_closure';
    /*
     * ancestor列名,默认ancestor
     */
    protected $ancestorColumn = 'ancestor';
    /*
     * descendant列名,默认descendant
     */
    protected $descendantColumn = 'descendant';
    /*
     * distance列名,默认distance
     */
    protected $distanceColumn = 'distance';
    /*
     * parent列名,默认parent,此列是计算生成,不在数据库存储
     */
    protected $parentColumn = 'parent';
    
}
```

接下来,你就可以自由的使用`ClosureTable`带来的功能了.

## License

[MIT](https://github.com/jiaxincui/closure-table/blob/master/LICENSE.md) © [JiaxinCui](https://github.com/jiaxincui)

