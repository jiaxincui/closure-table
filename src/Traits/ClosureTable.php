<?php

namespace Jiaxincui\ClosureTable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    public static function booted(): void
    {
        parent::booted();

        static::updating(function (self $model) {
            if ($model->isDirty($model->getParentColumn())) {
                $model->updateClosure();
            }
        });

        static::created(function (self $model) {
            $model->insertClosure($model->getParentKey());
        });

        static::deleting(function (self $model) {
            $model->deleteObservers();
        });

        if (method_exists(new static, 'restored')) {
            static::restored(function (self $model) {
                $model->insertClosure($model->getParentKey());
            });
        }
    }

    /**
     * Get this relation closure table
     *
     * @return string
     */
    protected function getClosureTable(): string
    {
        return $this->closureTable ?? str_replace('\\', '', Str::snake(class_basename($this))) . '_closure';
    }

    /**
     * @return string
     */
    protected function getPrefixedClosureTable(): string
    {
        return DB::connection($this->connection)->getTablePrefix() . $this->getClosureTable();
    }

    /**
     * @return string
     */
    protected function getPrefixedTable(): string
    {
        return DB::connection($this->connection)->getTablePrefix() . $this->getTable();
    }

    /**
     * Get this closure table ancestor column
     *
     * @return string
     */
    protected function getAncestorColumn(): string
    {
        return $this->ancestorColumn ?? 'ancestor';
    }

    /**
     * Get this closure table descendant column
     *
     * @return string
     */
    protected function getDescendantColumn(): string
    {
        return $this->descendantColumn ?? 'descendant';
    }

    /**
     * Get this closure table distance(hierarchy) column
     *
     * @return string
     */
    protected function getDistanceColumn(): string
    {
        return $this->distanceColumn ?? 'distance';
    }

    /**
     * Get parent column
     *
     * @return string
     */
    protected function getParentColumn(): string
    {
        return $this->parentColumn ?? 'parent';
    }

    /**
     * Get ancestor column with table name
     *
     * @return string
     */
    protected function getQualifiedAncestorColumn(): string
    {
        return $this->getClosureTable() . '.' . $this->getAncestorColumn();
    }

    /**
     * Get descendant column with table name
     *
     * @return string
     */
    protected function getQualifiedDescendantColumn(): string
    {
        return $this->getClosureTable() . '.' . $this->getDescendantColumn();
    }

    /**
     * Get Distance column with table name
     *
     * @return string
     */
    protected function getQualifiedDistanceColumn(): string
    {
        return $this->getClosureTable() . '.' . $this->getDistanceColumn();
    }

    /**
     * @return string
     */
    protected function getQualifiedParentColumn(): string
    {
        return $this->getTable() . '.' . $this->getParentColumn();
    }

    /**
     * @return mixed
     */
    protected function getParentKey(): mixed
    {
        return $this->getAttribute($this->getParentColumn());
    }

    /**
     * @param int|string|null $key
     * @return void
     */
    protected function setParentKey(int|string|null $key): void
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
    protected function joinRelationBy(string $column, bool $withSelf = false): Builder
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
    protected function joinRelationSelf(): Builder
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
    protected function joinWithoutClosure(): Builder
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
     * @param int|string|null $ancestorId
     * @return void
     */
    protected function insertClosure(int|string|null $ancestorId = null): void
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
            WHERE tbl.{$descendantColumn} = ?
            UNION
            SELECT {$descendantId}, {$descendantId}, 0
        ";

        DB::connection($this->connection)->insert($sql, array($ancestorId));
    }

    /**
     * @param string|null $with
     * @return void
     */
    protected function detachSelfRelation(string $with = null): void
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
    protected function deleteObservers(): void
    {
        if (!$this->exists) {
            throw new ModelNotFoundException();
        }
        $children = $this->getChildren();
        foreach ($children as $child) {
            $child->setParentKey(null);
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
    protected function detachRelationships(): void
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
     * @param int|string|null $parentKey
     * @return void
     */
    protected function attachTreeTo(int|string|null $parentKey = null): void
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
            FROM (SELECT * FROM {$prefixedTable} WHERE {$descendantColumn} = ?) as supertbl
            JOIN {$prefixedTable} as subtbl ON subtbl.{$ancestorColumn} = {$key}
        ";

        DB::connection($this->connection)->insert($sql, array($parentKey));
    }

    /**
     * Unbind self and descendants all relations
     *
     * @return void
     */
    protected function deleteRelationships(): void
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
     * @param int|string|Model $parameter
     * @return Model|static
     * @throws ModelNotFoundException
     */
    protected function parameter2Model(int|string|Model $parameter): Model|static
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
    protected function updateClosure(): void
    {
        if (is_null($this->getParentKey())) {
            return;
        }

        $parent = $this->parameter2Model($this->getParentKey());
        $parentKey = $parent->getKey();
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
    public static function deleteRedundancies(): void
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
    public function createChild(array $attributes): Model
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
    public function makeRoot(): void
    {
        if ($this->isRoot()) {
            return;
        }
        $this->setParentKey(null);
        $this->save();
    }

    /**
     * Associate a child or children to this model, accept model and int
     *
     * @param int|string|Model $child
     * @return void
     * @throws ClosureTableException
     * @throws Throwable
     */
    public function addChild(int|string|Model $child): void
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
    public function createSibling(array $attributes): Model
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $parentKey = $this->getParent()->getKey();
        $attributes[$this->getParentColumn()] = $parentKey;
        return $this->newQuery()->forceCreate($attributes);
    }

    /**
     * @param int|string|Model $sibling
     * @return void
     * @throws ClosureTableException|Throwable
     */
    public function addSibling(int|string|Model $sibling): void
    {
        $parent = $this->getParent();
        if (!$parent) {
            throw new ClosureTableException('failed because parent is not found');
        }
        $parent->addChild($sibling);
    }

    /**
     * @param int|string|Model $parent
     * @return void
     */
    public function moveTo(int|string|Model $parent): void
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
    public function perfectNode(): void
    {
        $parentKey = $this->getParentKey();
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
    public function perfectTree(): void
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
    public function queryAncestors(): Builder
    {
        return $this->joinRelationBy('ancestor');
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getAncestors(array $columns = ['*']): Collection
    {
        return $this->queryAncestors()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryAncestorsAndSelf(): Builder
    {
        return $this->joinRelationBy('ancestor', true);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getAncestorsAndSelf(array $columns = ['*']): Collection
    {
        return $this->queryAncestorsAndSelf()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryDescendants(): Builder
    {
        return $this->joinRelationBy('descendant');
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getDescendants(array $columns = ['*']): Collection
    {
        return $this->queryDescendants()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryDescendantsAndSelf(): Builder
    {
        return $this->joinRelationBy('descendant', true);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getDescendantsAndSelf(array $columns = ['*']): Collection
    {
        return $this->queryDescendantsAndSelf()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryBesides(): Builder
    {
        $root = $this->getRoot();
        if (is_null($root)) {
            return $this->newQuery()->whereNull($this->getKeyName());
        }
        $keyName = $this->getKeyName();
        $descendant = $this->getQualifiedDescendantColumn();
        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName)->toArray();
        return $root
            ->joinRelationBy('descendant', true)
            ->whereNotIn($descendant, $ids);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getBesides(array $columns = ['*']): Collection
    {
        return $this->queryBesides()->get($columns);
    }

    /**
     * @return Builder
     */
    public function queryChildren(): Builder
    {
        $key = $this->getKey();
        return $this->newQuery()->where($this->getParentColumn(), $key);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getChildren(array $columns = ['*']): Collection
    {
        return $this->queryChildren()->get($columns);
    }

    /**
     * @param array $columns
     * @return Model|null
     */
    public function getParent(array $columns = ['*']): ?Model
    {
        $parentKey = $this->getParentKey();
        return empty($parentKey) ? null : $this->newQuery()->find($parentKey, $columns);
    }

    /**
     * @param array $columns
     * @return Model|null
     */
    public function getRoot(array $columns = ['*']): ?Model
    {
        $parentColumn = $this->getParentColumn();
        return $this
            ->joinRelationBy('ancestor')
            ->whereNull($parentColumn)
            ->first($columns);
    }

    /**
     * @param int|string|Model $child
     * @return bool
     */
    public function isParentOf(Model|int|string $child): bool
    {
        $model = $this->parameter2Model($child);
        return !is_null($model->getParentKey()) && $this->getKey() === $model->getParentKey();
    }

    /**
     * @param int|string|Model $parent
     * @return bool
     */
    public function isChildOf(Model|int|string $parent): bool
    {
        $model = $this->parameter2Model($parent);
        return !is_null($this->getParentKey()) && $model->getKey() === $this->getParentKey();
    }

    /**
     * @param int|string|Model $descendant
     * @return bool
     */
    public function isAncestorOf(Model|int|string $descendant): bool
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($descendant);
        $ids = $this->getDescendants([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param int|string|Model $beside
     * @return bool
     */
    public function isBesideOf(Model|int|string $beside): bool
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
     * @param int|string|Model $ancestor
     * @return bool
     */
    public function isDescendantOf(Model|int|string $ancestor): bool
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($ancestor);
        $ids = $this->getAncestors([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param int|string|Model $sibling
     * @return bool
     */
    public function isSiblingOf(Model|int|string $sibling): bool
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
    public function getTree(array $sort = [], string $childrenColumn = 'children', array $columns = ['*']): array
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
    public function getBesideTree(array $sort = [], string $childrenColumn = 'children', array $columns = ['*']): array
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
    public function querySiblings(): Builder
    {
        $key = $this->getKey();
        $keyName = $this->getQualifiedKeyName();
        return $this->querySiblingsAndSelf()->where($keyName, '!=', $key);
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getSiblings(array $columns = ['*']): Collection
    {
        return $this->querySiblings()->get($columns);
    }

    /**
     * @return Builder
     */
    public function querySiblingsAndSelf(): Builder
    {
        $parent = $this->getParent();
        return is_null($parent) ? static::onlyRoot(): $parent->queryChildren();
    }

    /**
     * @param array $columns
     * @return Collection
     */
    public function getSiblingsAndSelf(array $columns = ['*']): Collection
    {
        return $this->querySiblingsAndSelf()->get($columns);
    }

    /**
     * This model is root
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return is_null($this->getParentKey());
    }

    /**
     * This model is leafed
     *
     * @return bool
     */
    public function isLeaf(): bool
    {
        return $this->queryChildren()->count() === 0;
    }

    /**
     * @return bool
     */
    public function isIsolated(): bool
    {
        $key = $this->getKey();
        $keyName = $this->getKeyName();
        $ids = $this->joinWithoutClosure()->get([$keyName])->pluck($keyName)->toArray();
        return in_array($key, $ids);
    }

    /**
     * @return Builder
     */
    public static function onlyIsolated(): Builder
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
    public static function onlyRoot(): Builder
    {
        $instance = new static;
        $parentColumn = $instance->getParentColumn();
        return $instance->newQuery()
            ->whereNull($parentColumn);
    }

    /**
     * @param array $models
     * @return CollectionExtension
     */
    public function newCollection(array $models = []): CollectionExtension
    {
        return new CollectionExtension($models);
    }
}
