<?php

namespace Jiaxincui\ClosureTable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jiaxincui\ClosureTable\Exceptions\ClosureTableException;
use Jiaxincui\ClosureTable\Extensions\CollectionExtension;
use Throwable;

/**
 * @mixin Model
 */
trait ClosureTable
{
    /**
     * Eloquent Listener
     */
    public static function boot()
    {
        parent::boot();

        static::updating(function (Model $model) {
            if ($model->isDirty($model->getParentColumn())) {
                $model->updateClosure();
            }
        });

        static::created(function (Model $model) {
            $model->insertClosure($model->getParentKey() ?: 0);
        });

        static::deleting(function (Model $model) {
            $model->deleteObservers();
        });

        if (method_exists(new static, 'restored')) {
            static::restored(function (Model $model) {
                $model->insertClosure($model->getParentKey() ?: 0);
            });
        }
    }

    /**
     * Get this relation closure table
     *
     * @return string
     */
    protected function getClosureTable()
    {
        return $this->closureTable ?? str_replace('\\', '', Str::snake(class_basename($this))) . '_closure';
    }

    /**
     * @return string
     */
    protected function getPrefixedClosureTable()
    {
        return DB::connection($this->connection)->getTablePrefix() . $this->getClosureTable();
    }

    /**
     * @return string
     */
    protected function getPrefixedTable()
    {
        return DB::connection($this->connection)->getTablePrefix() . $this->getTable();
    }

    /**
     * Get this closure table ancestor column
     *
     * @return string
     */
    protected function getAncestorColumn()
    {
        return $this->ancestorColumn ?? 'ancestor';
    }

    /**
     * Get this closure table descendant column
     *
     * @return string
     */
    protected function getDescendantColumn()
    {
        return $this->descendantColumn ?? 'descendant';
    }

    /**
     * Get this closure table distance(hierarchy) column
     *
     * @return string
     */
    protected function getDistanceColumn()
    {
        return $this->distanceColumn ?? 'distance';
    }

    /**
     * Get parent column
     *
     * @return string
     */
    protected function getParentColumn()
    {
        return $this->parentColumn ?? 'parent';
    }

    /**
     * Get ancestor column with table name
     *
     * @return string
     */
    protected function getQualifiedAncestorColumn()
    {
        return $this->getClosureTable() . '.' . $this->getAncestorColumn();
    }

    /**
     * Get descendant column with table name
     *
     * @return string
     */
    protected function getQualifiedDescendantColumn()
    {
        return $this->getClosureTable() . '.' . $this->getDescendantColumn();
    }

    /**
     * Get Distance column with table name
     *
     * @return string
     */
    protected function getQualifiedDistanceColumn()
    {
        return $this->getClosureTable() . '.' . $this->getDistanceColumn();
    }

    /**
     * @return string
     */
    protected function getQualifiedParentColumn()
    {
        return $this->getTable() . '.' . $this->getParentColumn();
    }

    /**
     * @return string
     */
    protected function getParentKey()
    {
        return $this->getAttribute($this->getParentColumn());
    }

    /**
     * @param string $key
     * @return void
     */
    protected function setParentKey(string $key)
    {
        $this->attributes[$this->getParentColumn()] = $key;
    }

    /**
     * Join closure table
     *
     * @param string $column
     * @param bool $withSelf
     * @return Builder
     */
    protected function joinRelationBy(string $column, bool $withSelf = false)
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();

        $query = $this->newQuery();
        switch ($column) {
            case 'ancestor':
                $query = $query->join($closureTable, $ancestor, '=', $keyName)
                    ->where($descendant, '=', $key);
                break;

            case 'descendant':
                $query = $query->join($closureTable, $descendant, '=', $keyName)
                    ->where($ancestor, '=', $key);
                break;
        }

        $operator = $withSelf === true ? '>=' : '>';

        return $query->where($distance, $operator, 0);
    }

    /**
     * Get self relation
     * @return Builder
     */
    protected function joinRelationSelf()
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();
        return $this->newQuery()
            ->join($closureTable, $keyName, '=', $ancestor)
            ->where($ancestor, $key)
            ->where($descendant, $key)
            ->where($distance, 0);
    }

    /**
     * Get without closure table
     *
     * @return Builder
     */
    protected function joinWithoutClosure()
    {
        $keyName = $this->getQualifiedKeyName();

        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        return $this->newQuery()
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
    }

    /**
     * Insert node relation to closure table
     *
     * @param int $ancestorId
     * @return void
     */
    protected function insertClosure(int $ancestorId = 0)
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $descendantId = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distanceColumn = $this->getDistanceColumn();

        $sql = "
            INSERT INTO {$prefixedTable} ({$ancestorColumn}, {$descendantColumn}, {$distanceColumn})
            SELECT tbl.{$ancestorColumn}, {$descendantId}, tbl.{$distanceColumn}+1
            FROM {$prefixedTable} AS tbl
            WHERE tbl.{$descendantColumn} = {$ancestorId}
            UNION
            SELECT {$descendantId}, {$descendantId}, 0
        ";

        DB::connection($this->connection)->insert($sql);
    }

    /**
     * @param string|null $with
     * @return void
     */
    protected function detachSelfRelation(string $with = null)
    {
        $key = $this->getKey();
        $table = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        switch ($with) {
            case 'ancestor':
                DB::table($table)->where($descendantColumn, $key)->delete();
                break;
            case 'descendant':
                DB::table($table)->where($ancestorColumn, $key)->delete();
                break;
            default:
                DB::table($table)->where($descendantColumn, $key)->orWhere($ancestorColumn, $key)->delete();
        }
    }

    /**
     * @return void
     */
    protected function deleteObservers()
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }
        $children = $this->getChildren();
        foreach ($children as $child) {
            $child->setParentKey(0);
            $child->save();
        }
        $this->detachRelationships();
        $this->detachSelfRelation();
    }

    /**
     * Unbind self to ancestor and descendants to ancestor relations
     *
     * @return void
     */
    protected function detachRelationships()
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();

        $sql = "
            DELETE FROM {$prefixedTable}
            WHERE {$descendantColumn} IN (
              SELECT d FROM (
                SELECT {$descendantColumn} as d FROM {$prefixedTable}
                WHERE {$ancestorColumn} = {$key}
              ) as dct
            )
            AND {$ancestorColumn} IN (
              SELECT a FROM (
                SELECT {$ancestorColumn} AS a FROM {$prefixedTable}
                WHERE {$descendantColumn} = {$key}
                AND {$ancestorColumn} <> {$key}
              ) as ct
            )
        ";

        DB::connection($this->connection)->delete($sql);
    }

    /**
     * Associate self to ancestor and descendants to ancestor relations
     *
     * @param int $parentKey
     * @return void
     */
    protected function attachTreeTo(int $parentKey = 0)
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distanceColumn = $this->getDistanceColumn();

        $sql = "
            INSERT INTO {$prefixedTable} ({$ancestorColumn}, {$descendantColumn}, {$distanceColumn})
            SELECT supertbl.{$ancestorColumn}, subtbl.{$descendantColumn}, supertbl.{$distanceColumn}+subtbl.{$distanceColumn}+1
            FROM (SELECT * FROM {$prefixedTable} WHERE {$descendantColumn} = {$parentKey}) as supertbl
            JOIN {$prefixedTable} as subtbl ON subtbl.{$ancestorColumn} = {$key}
        ";

        DB::connection($this->connection)->insert($sql);
    }

    /**
     * Unbind self and descendants all relations
     *
     * @return void
     */
    protected function deleteRelationships()
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $sql = "
            DELETE FROM {$prefixedTable}
            WHERE {$descendantColumn} IN (
            SELECT d FROM (
              SELECT {$descendantColumn} as d FROM {$prefixedTable}
                WHERE {$ancestorColumn} = {$key}
              ) as dct
            )
        ";

        DB::connection($this->connection)->delete($sql);
    }

    /**
     * Convert parameter
     *
     * @param int|Model $parameter
     * @return Model|static
     * @throws ModelNotFoundException
     */
    protected function parameter2Model(int|Model $parameter)
    {
        if ($parameter instanceof Model) {
            return $parameter;
        }
        return $this->newQuery()->findOrFail($parameter);
    }

    /**
     * @return void
     * @throws ClosureTableException|Throwable
     */
    protected function updateClosure()
    {
        if ($this->getParentKey()) {
            $parent = $this->parameter2Model($this->getParentKey());
            $parentKey = $parent->getKey();
        } else {
            $parentKey = 0;
        }

        $ids = $this->getDescendantsAndSelf([$this->getKeyName()])->pluck($this->getKeyName())->toArray();

        if (in_array($parentKey, $ids)) {
            throw new ClosureTableException('Can\'t move to descendant');
        }
        DB::connection($this->connection)->transaction(function () use ($parentKey) {
            try {
                $this->detachRelationships();
                $this->attachTreeTo($parentKey);
            } catch (\Exception $e) {
                throw new ClosureTableException($e->getMessage());
            }
        });
    }

    /**
     * Delete nonexistent relations
     *
     * @return void
     */
    public static function deleteRedundancies()
    {
        $instance = new static;

        $segment = '';
        if (method_exists($instance, 'bootSoftDeletes') && method_exists($instance, 'getDeletedAtColumn')) {
            $segment = 'OR t.' . $instance->getDeletedAtColumn() . ' IS NOT NULL';
        }
        $table = $instance->getPrefixedTable();
        $prefixedTable = $instance->getPrefixedClosureTable();
        $ancestorColumn = $instance->getAncestorColumn();
        $descendantColumn = $instance->getDescendantColumn();
        $keyName = $instance->getKeyName();
        $sql = "
            DELETE ct FROM {$prefixedTable} ct
            WHERE {$descendantColumn} IN (
              SELECT d FROM (
                SELECT {$descendantColumn} as d FROM {$prefixedTable}
                LEFT JOIN {$table} t 
                ON {$descendantColumn} = t.{$keyName}
                WHERE t.{$keyName} IS NULL {$segment}
              ) as dct
            )
            OR {$ancestorColumn} IN (
              SELECT d FROM (
                SELECT {$ancestorColumn} as d FROM {$prefixedTable}
                LEFT JOIN {$table} t 
                ON {$ancestorColumn} = t.{$keyName}
                WHERE t.{$keyName} IS NULL {$segment}
              ) as act
            )
        ";
        DB::connection($instance->connection)->delete($sql);
    }

    /**
     * Create a child from Array
     *
     * @param array $attributes
     * @return Model
     * @throws ClosureTableException
     */
    public function createChild(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $parentKey = $this->getKey();
        $attributes[$this->getParentColumn()] = $parentKey;
        return $this->newQuery()->forceCreate($attributes);
    }

    /**
     * Make this model to root
     *
     * @return void
     */
    public function makeRoot()
    {
        if ($this->isRoot()) {
            return;
        }
        $this->setParentKey(0);
        $this->save();
    }

    /**
     * Associate a child or children to this model, accept model and int
     *
     * @param int|Model $child
     * @return void
     * @throws ClosureTableException
     * @throws Throwable
     */
    public function addChild(int|Model $child)
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $keyName = $this->getKeyName();
        $key = $this->getKey();
        $ids = $this->getAncestorsAndSelf([$keyName])->pluck($keyName)->toArray();

        $model = $this->parameter2Model($child);
        if (in_array($model->getKey(), $ids)) {
            throw new ClosureTableException('Children can\'t be ancestor');
        }
        $model->setAttribute($this->getParentColumn(), $key);
        $model->save();
    }

    /**
     * @param array $attributes
     * @return Model
     * @throws ClosureTableException
     */
    public function createSibling(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $parentKey = $this->getParent()->getKey();
        $attributes[$this->getParentColumn()] = $parentKey;
        return $this->newQuery()->forceCreate($attributes);
    }

    /**
     * @param int|Model $sibling
     * @return void
     * @throws ClosureTableException|Throwable
     */
    public function addSibling(int|Model $sibling)
    {
        $parent = $this->getParent();
        if (!$parent) {
            throw new ClosureTableException('failed because parent is not found');
        }
        $parent->addChild($sibling);
    }

    /**
     * @param int|Model $parent
     * @return void
     */
    public function moveTo(int|Model $parent)
    {
        $model = $this->parameter2Model($parent);
        $this->setParentKey($model->getKey());
        $this->save();
    }

    /**
     * fix the model to ancestors relation. If you need to fix all, cycle all
     *
     * @return void
     * @throws Throwable
     */
    public function perfectNode()
    {
        $parentKey = $this->getParentKey() ?: 0;
        DB::connection($this->connection)->transaction(function () use ($parentKey) {
            $this->detachSelfRelation('ancestor');
            $this->insertClosure($parentKey);
        });
    }

    /**
     * Each and fix tree's every item, If your tree too large careful with it
     *
     * @return void
     */
    public function perfectTree()
    {
        $this->getDescendants()->each(function ($item) {
            $item->perfectNode();
        });
    }

    /**
     * Get ancestors query
     *
     * @return Builder
     */
    public function queryAncestors()
    {
        return $this->joinRelationBy('ancestor');
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getAncestors(array $columns = ['*'])
    {
        return $this->queryAncestors()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryAncestorsAndSelf()
    {
        return $this->joinRelationBy('ancestor', true);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getAncestorsAndSelf(array $columns = ['*'])
    {
        return $this->queryAncestorsAndSelf()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryDescendants()
    {
        return $this->joinRelationBy('descendant');
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getDescendants(array $columns = ['*'])
    {
        return $this->queryDescendants()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryDescendantsAndSelf()
    {
        return $this->joinRelationBy('descendant', true);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getDescendantsAndSelf(array $columns = ['*'])
    {
        return $this->queryDescendantsAndSelf()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryBesides()
    {
        $keyName = $this->getKeyName();
        $descendant = $this->getQualifiedDescendantColumn();
        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName)->toArray();
        return $this->getRoot()
            ->joinRelationBy('descendant', true)
            ->whereNotIn($descendant, $ids);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getBesides(array $columns = ['*'])
    {
        return $this->queryBesides()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryChildren()
    {
        $key = $this->getKey();
        return $this->newQuery()->where($this->getParentColumn(), $key);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getChildren(array $columns = ['*'])
    {
        return $this->queryChildren()->get($columns);
    }

    /**
     * @param array $columns
     * @return Model|static|null
     */
    public function getParent(array $columns = ['*'])
    {
        $parentKey = $this->getParentKey();
        return $this->newQuery()->find($parentKey, $columns);
    }

    /**
     * @param array $columns
     * @return static|null
     */
    public function getRoot(array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return $this;
        }
        $parentColumn = $this->getParentColumn();
        return $this
            ->joinRelationBy('ancestor')
            ->where($parentColumn, 0)
            ->orWhere(function ($query) use ($parentColumn) {
                $query->whereNull($parentColumn);
            })
            ->first($columns);
    }

    /**
     * @param Model|int $child
     * @return bool
     */
    public function isParentOf($child)
    {
        $model = $this->parameter2Model($child);
        return $this->getKey() === $model->getParentKey();
    }

    /**
     * @param Model|int $parent
     * @return bool
     */
    public function isChildOf($parent)
    {
        $model = $this->parameter2Model($parent);
        return $model->getKey() === $this->getParentKey();
    }

    /**
     * @param Model|int $descendant
     * @return bool
     */
    public function isAncestorOf($descendant)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($descendant);
        $ids = $this->getDescendants([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param Model|int $beside
     * @return bool
     */
    public function isBesideOf($beside)
    {
        if ($this->isRoot()) {
            return false;
        }
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($beside);
        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName)->toArray();
        return !in_array($model->getKey(), $ids);
    }

    /**
     * @param Model|int $ancestor
     * @return bool
     */
    public function isDescendantOf($ancestor)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($ancestor);
        $ids = $this->getAncestors([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param Model|int $sibling
     * @return bool
     */
    public function isSiblingOf($sibling)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($sibling);
        $ids = $this->getSiblings([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param array $sort
     * @param string $childrenColumn
     * @param array $columns
     * @return array
     */
    public function getTree(array $sort = [], string $childrenColumn = 'children', array $columns = ['*'])
    {
        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (in_array('*', $columns)) {
            $columns = ['*'];
        } elseif (!in_array($parentColumn, $columns)) {
            $columns[] = $parentColumn;
        }

        if (!empty($sort)) {
            $sortKey = $sort[0] ?? 'sort';
            $sortMode = $sort[1] ?? 'asc';
            return $this
                ->joinRelationBy('descendant', true)
                ->orderBy($sortKey, $sortMode)
                ->get($columns)
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->joinRelationBy('descendant', true)
            ->get($columns)
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @param array $sort
     * @param string $childrenColumn
     * @param array $columns
     * @return array
     */
    public function getBesideTree(array $sort = [], string $childrenColumn = 'children', array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return [];
        }
        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (in_array('*', $columns)) {
            $columns = ['*'];
        } elseif (!in_array($parentColumn, $columns)) {
            $columns[] = $parentColumn;
        }

        if (!empty($sort)) {
            $sortKey = $sort[0] ?? 'sort';
            $sortMode = $sort[1] ?? 'asc';
            return $this
                ->queryBesides()
                ->orderBy($sortKey, $sortMode)
                ->get($columns)
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->queryBesides()
            ->get($columns)
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @return Builder
     */
    public function querySiblings()
    {
        if ($this->getParentKey()) {
            $parent = $this->getParent();
            $key = $this->getKey();
            $keyName = $this->getKeyName();
            return $parent->queryChildren()->whereNotIn($keyName, [$key]);
        } else {
            return self::onlyRoot();
        }

    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getSiblings(array $columns = ['*'])
    {
        return $this->querySiblings()->get($columns);
    }

    /**
     * @return Builder
     */
    public function querySiblingsAndSelf()
    {
        $parent = $this->getParent();
        return $parent->queryChildren();
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getSiblingsAndSelf(array $columns = ['*'])
    {
        return $this->querySiblingsAndSelf()->get($columns);
    }

    /**
     * This model is root
     *
     * @return bool
     */
    public function isRoot()
    {
        return !$this->getParentKey();
    }

    /**
     * This model is leaf
     *
     * @return bool
     */
    public function isLeaf()
    {
        return $this->queryChildren()->count() === 0;
    }

    /**
     * @return bool
     */
    public function isIsolated()
    {
        $key = $this->getKey();
        $keyName = $this->getKeyName();
        $ids = $this->joinWithoutClosure()->get([$keyName])->pluck($keyName)->toArray();
        return in_array($key, $ids);
    }

    /**
     * @return Builder
     */
    public static function onlyIsolated()
    {
        $instance = new static;
        $keyName = $instance->getQualifiedKeyName();
        $closureTable = $instance->getClosureTable();
        $ancestor = $instance->getQualifiedAncestorColumn();
        $descendant = $instance->getQualifiedDescendantColumn();
        return $instance->newQuery()
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
    }

    /**
     * @return Builder
     */
    public static function onlyRoot()
    {
        $instance = new static;
        $parentColumn = $instance->getParentColumn();
        return $instance->newQuery()
            ->where($parentColumn, 0)
            ->orWhere(function ($query) use ($parentColumn) {
                $query->whereNull($parentColumn);
            });
    }

    /**
     * @param array $models
     * @return CollectionExtension
     */
    public function newCollection(array $models = [])
    {
        return new CollectionExtension($models);
    }
}
