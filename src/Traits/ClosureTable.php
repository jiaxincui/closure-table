<?php
namespace Jiaxincui\ClosureTable\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jiaxincui\ClosureTable\Exceptions\ClosureTableException;
use Jiaxincui\ClosureTable\Extensions\CollectionExtension;

trait ClosureTable
{
    /**
     * Deleted Listener
     */
    public static function boot()
    {
        parent::boot();

        static::deleting(function (Model $model) {
            $model->deleteRelationships();
        });
    }

    /**
     * Get this relation closure table
     *
     * @return string
     */
    protected function getClosureTable()
    {
        if (! isset($this->closureTable)) {
            return str_replace('\\', '', Str::snake(class_basename($this))) . '_closure';
        }
        return $this->closureTable;
    }

    /**
     * Get this closure table ancestor column
     *
     * @return string
     */
    protected function getAncestorColumn()
    {
        if (! isset($this->ancestorColumn)) {
            return 'ancestor';
        }
        return $this->ancestorColumn;
    }

    /**
     * Get this closure table descendant column
     *
     * @return string
     */
    protected function getDescendantColumn()
    {
        if (! isset($this->descendantColumn)) {
            return 'descendant';
        }
        return $this->descendantColumn;
    }

    /**
     * Get this closure table distance(hierarchy) column
     *
     * @return string
     */
    protected function getDistanceColumn()
    {
        if (! isset($this->distanceColumn)) {
            return 'distance';
        }
        return $this->distanceColumn;
    }

    /**
     * Get parent column
     *
     * @return string
     */
    protected function getParentColumn()
    {
        if (! isset($this->parentColunm)) {
            return 'parent';
        }
        return $this->parentColunm;
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
     * Join closure table
     *
     * @param $column
     * @param bool $withSelf
     * @return mixed
     */
    protected function joinRelationBy($column, $withSelf = false)
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();

        switch ($column) {
            case 'ancestor':
                $query = $this->join($closureTable, $ancestor, '=', $keyName)
                    ->where($descendant, '=', $key);
                break;

            case 'descendant':
                $query = $this->join($closureTable, $descendant, '=', $keyName)
                    ->where($ancestor, '=', $key);
                break;
        }

        $operator = ($withSelf === true ? '>=' : '>');

        $query->where($distance, $operator, 0);

        return $query;
    }

    /**
     * Get self relation
     *
     * @return mixed
     */
    protected function joinRelationSelf()
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();
        $query = $this
            ->join($closureTable, $keyName, '=', $ancestor)
            ->where($ancestor, $key)
            ->where($descendant, $key)
            ->where($distance, 0);
        return $query;
    }

    /**
     * Get parent or children
     *
     * @param $column
     * @return mixed
     */
    protected function joinRelationNearBy($column)
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();

        switch ($column) {
            case 'ancestor':
                $query = $this->join($closureTable, $ancestor, '=', $keyName)
                    ->where($descendant, '=', $key);
                break;

            case 'descendant':
                $query = $this->join($closureTable, $descendant, '=', $keyName)
                    ->where($ancestor, '=', $key);
                break;
        }

        $query->where($distance, 1);

        return $query;
    }

    /**
     * Get without closure table
     *
     * @return mixed
     */
    protected function joinWithoutClosure()
    {
        $keyName = $this->getQualifiedKeyName();

        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $query = $this
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
        return $query;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeIsolated($query)
    {
        $keyName = $this->getQualifiedKeyName();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        return $query
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeWithParent($query)
    {
        $keyName = $this->getKeyName();
        $closureTable = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $parentColumn = $this->getParentColumn();
        $distanceColumn = $this->getDistanceColumn();

        return $query->select(DB::raw("*, IFNULL((
                SELECT c.{$ancestorColumn} FROM {$closureTable} AS c 
                WHERE {$descendantColumn}={$keyName} 
                AND {$distanceColumn}=1
                ), 0) AS {$parentColumn}
                ")
        );
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeOnlyRoot($query)
    {
        $keyName = $this->getQualifiedKeyName();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();
        return $query->whereExists(function($query) use ($closureTable, $keyName, $descendant, $ancestor, $distance) {
            $query->select(DB::raw("{$descendant}, SUM($distance) AS dcs"))
                ->from($closureTable)
                ->whereRaw("{$keyName}={$descendant}")
                ->groupBy($descendant)
                ->havingRaw("dcs=0");
        });
    }

    /**
     * Insert node relation to closure table
     *
     * @param $ancestorId
     * @param $descendantId
     * @return bool
     */
    protected function insertClosure($ancestorId, $descendantId)
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $table = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distance = $this->getDistanceColumn();

        $query = "
            INSERT INTO {$table} ({$ancestorColumn}, {$descendantColumn}, {$distance})
            SELECT tbl.{$ancestorColumn}, {$descendantId}, tbl.{$distance}+1
            FROM {$table} AS tbl
            WHERE tbl.{$descendantColumn} = {$ancestorId}
            UNION ALL
            SELECT {$descendantId}, {$descendantId}, 0
            ON DUPLICATE KEY UPDATE {$distance} = VALUES ({$distance})
        ";

        DB::connection($this->connection)->insert($query);
        return true;
    }

    /**
     * Insert self relation to closure table
     *
     * @return bool
     */
    protected function insertSelfClosure()
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $key = $this->getKey();
        $table = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distance = $this->getDistanceColumn();
        $query = "
            INSERT INTO {$table} ({$ancestorColumn}, {$descendantColumn}, {$distance})
            VALUES ({$key}, {$key}, 0)
            ON DUPLICATE KEY UPDATE {$distance} = VALUES ({$distance})
        ";
        DB::connection($this->connection)->insert($query);
        return true;
    }

    /**
     * Unbind self to ancestor and descendants to ancestor relations
     *
     * @return bool
     */
    protected function detachRelationships()
    {
        if (! $this->exists) throw new ModelNotFoundException();

        if ($this->joinRelationSelf()->count() === 0) return false;

        $key = $this->getKey();
        $table = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();

        $query = "
            DELETE FROM {$table}
            WHERE {$descendantColumn} IN (
              SELECT d FROM (
                SELECT {$descendantColumn} as d FROM {$table}
                WHERE {$ancestorColumn} = {$key}
              ) as dct
            )
            AND {$ancestorColumn} IN (
              SELECT a FROM (
                SELECT {$ancestorColumn} AS a FROM {$table}
                WHERE {$descendantColumn} = {$key}
                AND {$ancestorColumn} <> {$key}
              ) as ct
            )
        ";

        DB::connection($this->connection)->delete($query);
        return true;
    }

    /**
     * Associate self to ancestor and descendants to ancestor relations
     *
     * @param null $ancestorId
     * @return bool
     */
    protected function attachTreeTo($ancestorId = null)
    {
        if (! $this->exists) throw new ModelNotFoundException();

        if (is_null($ancestorId) || (int) $ancestorId === 0) return false;

        if ($this->joinRelationSelf()->count() === 0) $this->insertSelfClosure();

        $key = $this->getKey();
        $table = $this->getClosureTable();
        $ancestor = $this->getAncestorColumn();
        $descendant = $this->getDescendantColumn();
        $distance = $this->getDistanceColumn();
        $query = "
            INSERT INTO {$table} ({$ancestor}, {$descendant}, {$distance})
            SELECT supertbl.{$ancestor}, subtbl.{$descendant}, supertbl.{$distance}+subtbl.{$distance}+1
            FROM {$table} as supertbl
            CROSS JOIN {$table} as subtbl
            WHERE supertbl.{$descendant} = {$ancestorId}
            AND subtbl.{$ancestor} = {$key}
            ON DUPLICATE KEY UPDATE {$distance} = VALUES ({$distance})
        ";

        DB::connection($this->connection)->insert($query);
        return true;
    }

    /**
     * Unbind self and descendants all relations
     *
     * @return bool
     */
    protected function deleteRelationships()
    {
        if (! $this->exists) throw new ModelNotFoundException();

        if ($this->joinRelationSelf()->count() === 0) return false;

        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $query = "
                  DELETE FROM {$closureTable}
                  WHERE {$descendantColumn} IN (
                    SELECT d FROM (
                      SELECT {$descendantColumn} as d FROM {$closureTable}
                        WHERE {$ancestorColumn} = {$key}
                      ) as dct
                    )
                  ";

        DB::connection($this->connection)->delete($query);
        return true;
    }

    /**
     * Convert parameter
     *
     * @param $parameter
     * @return Model|null
     */
    protected function parameter2Model($parameter)
    {
        $model = null;
        if ($parameter instanceof Model) {
            $model = $parameter;
        } elseif (is_numeric($parameter)) {
            $model = $this->findOrFail($parameter);
        } else {
            throw (new ModelNotFoundException)->setModel(
                get_class($this->model), $parameter
            );
        }
        return $model;
    }

    /**
     * Delete nonexistent relations
     *
     * @return bool
     */
    public static function deleteRedundancies()
    {
        $instance = new static;

        $segment = '';
        if (method_exists($instance, 'bootSoftDeletes')) {
            $segment = 'OR t.'.$instance->getDeletedAtColumn().' IS NOT NULL';
        }
        $table = $instance->getTable();
        $closureTable = $instance->getClosureTable();
        $ancestorColumn = $instance->getAncestorColumn();
        $descendantColumn = $instance->getDescendantColumn();
        $keyName = $instance->getKeyName();
        $deleteAncestor = "
                DELETE ct FROM {$closureTable} ct
                    LEFT JOIN {$table} t ON ct.{$ancestorColumn} = t.{$keyName}
                    WHERE t.{$keyName} IS NULL {$segment}
              
        ";
        $deleteDescendant = "
                DELETE ct FROM {$closureTable} ct
                    LEFT JOIN {$table} t ON ct.{$descendantColumn} = t.{$keyName}
                    WHERE t.{$keyName} IS NULL {$segment}
        ";
        DB::connection($instance->connection)->delete($deleteAncestor);
        DB::connection($instance->connection)->delete($deleteDescendant);
        return true;
    }

    /**
     * @return bool
     */
    public function attachSelf()
    {
        return $this->insertSelfClosure();
    }

    /**
     * @return bool
     */
    public function detachSelf()
    {
        return $this->deleteRelationships();
    }

    /**
     * Create a child from Array
     *
     * @param array $attributes
     * @return mixed
     * @throws ClosureTableException
     */
    public function createChild(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) throw new ClosureTableException('Model is not a node');

        $parent_id = $this->getKey();
        $child = $this->create($attributes);
        return $this->insertClosure($parent_id, $child->getKey()) ? $child : null;
    }

    /**
     * Make this model to root
     *
     * @return bool
     */
    public function makeRoot()
    {
        return $this->insertSelfClosure() && $this->detachRelationships();
    }

    /**
     * Associate a child or children to this model, accept model and string and array
     *
     * @param $children
     * @return bool
     * @throws ClosureTableException
     */
    public function addChild($children)
    {
        if ($this->joinRelationSelf()->count() === 0) throw new ClosureTableException('Model is not a node');

        $keyName = $this->getKeyName();
        $key = $this->getKey();
        $ids = $this->getAncestorsAndSelf([$keyName])->pluck($keyName)->toArray();
        if (! is_array($children)) {
            $children = array($children);
        }
        DB::connection($this->connection)->transaction(function () use ($children, $key, $ids) {
            foreach ($children as $child) {
                $model = $this->parameter2Model($child);
                if (in_array($model->getKey(), $ids)) {
                    throw new ClosureTableException('Children can\'t be ancestor');
                }
                $this->insertClosure($key, $model->getKey());
            }
        });

        return true;
    }

    /**
     * @param array $attributes
     * @return mixed
     * @throws ClosureTableException
     */
    public function createSibling(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) throw new ClosureTableException('Model is not a node');

        $parent_id = $this->getParent()->getKey();
        $sibling = $this->create($attributes);
        return $this->insertClosure($parent_id, $sibling->getKey()) ? $sibling : null;
    }

    /**
     * @param $siblings
     * @return bool
     */
    public function addSiblings($siblings)
    {
        $parent = $this->getParent();
        if (! $parent) return false;
        return $parent->addChild($siblings);
    }

    /**
     * @param $ancestor
     * @return bool
     * @throws ClosureTableException
     */
    public function moveTo($ancestor)
    {
        $ancestor = $this->parameter2Model($ancestor);

        if ($ancestor->joinRelationSelf()->count() === 0) return false;

        $ancestorId = $ancestor->getKey();
        $ids = $this->getDescendantsAndSelf([$this->getKeyName()])->pluck($this->getKeyName())->toArray();

        if (in_array($ancestorId, $ids)) {
            throw new ClosureTableException('Can\'t move to descendant');
        }
        DB::connection($this->connection)->transaction(function () use ($ancestorId) {
            if ($this->joinRelationSelf()->count() > 0) {
                if (! $this->detachRelationships()) {
                    throw new ClosureTableException('Unbind relationships failed');
                }
            }
            if (! $this->attachTreeTo($ancestorId)) {
                throw new ClosureTableException('Associate tree failed');
            }
        });
        return true;
    }

    /**
     * add to parent
     *
     * @param $ancestor
     * @return bool
     */
    public function addTo($ancestor)
    {
        return $this->moveTo($ancestor);
    }

    /**
     * fix the model to ancestors relation. If you need to fix all, cycle all
     *
     * @return bool
     */
    public function perfectNode()
    {
        if ($this->isRoot()) {
            return true;
        }
        $parent = $this->getParent();

        if (! $this->attachTreeTo($parent->getKey())) {
            return false;
        }
        return true;
    }

    /**
     * Each and fix tree's every item, If your tree too large careful with it
     *
     * @return bool
     */
    public function perfectTree()
    {
        $result = true;
        $this->getDescendants()->each(function ($item) use ($result) {
            if (! $item->perfectNode()) {
                $result = false;
                return false;
            }
        });
        return $result;
    }

    /**
     * Get ancestors query
     *
     * @return mixed
     */
    public function queryAncestors()
    {
        return $this->joinRelationBy('ancestor');
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getAncestors(array $columns = ['*'])
    {
        return $this->queryAncestors()->orderBy('distance', 'desc')->get($columns);
    }

    /**
     * @return mixed
     */
    public function queryAncestorsAndSelf()
    {
        return $this->joinRelationBy('ancestor', true);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getAncestorsAndSelf(array $columns = ['*'])
    {
        return $this->queryAncestorsAndSelf()->get($columns);
    }

    /**
     * @return mixed
     */
    public function queryDescendants()
    {
        return $this->joinRelationBy('descendant');
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getDescendants(array $columns = ['*'])
    {
        return $this->queryDescendants()->get($columns);
    }

    /**
     * @return mixed
     */
    public function queryDescendantsAndSelf()
    {
        return $this->joinRelationBy('descendant', true);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getDescendantsAndSelf(array $columns = ['*'])
    {
        return $this->queryDescendantsAndSelf()->get($columns);
    }

    /**
     * @return mixed
     */
    public function queryBesides()
    {
        $keyName = $this->getKeyName();
        $descendant = $this->getQualifiedDescendantColumn();
        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName)->toArray();
        $root = $this->getRoot();
        return $root
            ->joinRelationBy('descendant', true)
            ->whereNotIn($descendant, $ids);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getBesides(array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return null;
        }
        return $this->queryBesides()->get($columns);
    }

    /**
     * @return mixed
     */
    public function queryChildren()
    {
        return $this->joinRelationNearBy('descendant');
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getChildren(array $columns = ['*'])
    {
        return $this->queryChildren()->get($columns);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getParent(array $columns = ['*'])
    {
        return $this->joinRelationNearBy('ancestor')->first($columns);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getRoot(array $columns = ['*'])
    {
        return $this->joinRelationBy('ancestor')->first($columns);
    }

    /**
     * @param $child
     * @return bool
     */
    public function isParentOf($child)
    {
        $model = $this->parameter2Model($child);
        $keyName = $this->getKeyName();
        $childrenIds = $this->joinRelationNearBy('descendant')->get([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $childrenIds);
    }

    /**
     * @param $parent
     * @return bool
     */
    public function isChildOf($parent)
    {
        $model = $this->parameter2Model($parent);
        $parentId = $this->joinRelationNearBy('ancestor')->first()->getKey();
        return $model->getKey() === $parentId;
    }

    /**
     * @param $descendant
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
     * @param $beside
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
        return ! in_array($model->getKey(), $ids);
    }

    /**
     * @param $ancestor
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
     * @param $sibling
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
     * @return mixed
     */
    public function getTree(array $sort = [], $childrenColumn = 'children', array $columns = ['*'])
    {
        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (! empty($sort)) {
            $sortKey = isset($sort[0]) ? $sort[0] : 'sort';
            $sortMode = isset($sort[1]) ? $sort[1]: 'asc';
            return $this
                ->joinRelationBy('descendant', true)
                ->withParent()
                ->orderBy($sortKey, $sortMode)
                ->get($columns)
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->joinRelationBy('descendant', true)
            ->withParent()
            ->get($columns)
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @param array $sort
     * @param string $childrenColumn
     * @param array $columns
     * @return array
     */
    public function getBesideTree(array $sort = [], $childrenColumn = 'children', array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return [];
        }
        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (! empty($sort)) {
            $sortKey = isset($sort[0]) ? $sort[0] : 'sort';
            $sortMode = isset($sort[1]) ? $sort[1]: 'asc';
            return $this
                ->queryBesides()
                ->withParent()
                ->orderBy($sortKey, $sortMode)
                ->get($columns)
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->queryBesides()
            ->withParent()
            ->get($columns)
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @return mixed
     */
    public function querySiblings()
    {
        $parent = $this->getParent();
        $key = $this->getKey();
        $keyName = $this->getKeyName();
        return $parent->queryChildren()->whereNotIn($keyName, [$key]);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getSiblings(array $columns = ['*'])
    {
        return $this->querySiblings()->get($columns);
    }

    /**
     * @return mixed
     */
    public function querySiblingsAndSelf()
    {
        $parent = $this->getParent();
        return $parent->queryChildren();
    }

    /**
     * @param array $columns
     * @return mixed
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
        return $this->getParent() === null && $this->joinRelationSelf()->count() > 0;
    }

    /**
     * This model is leaf
     *
     * @return bool
     */
    public function isLeaf()
    {
        return $this->joinRelationSelf()->count() > 0 && $this->getDescendants()->count() === 0;
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
     * Get isolated item
     *
     * @param array $columns
     * @return mixed
     */
    public static function getIsolated(array $columns = ['*'])
    {
        return self::isolated()->get($columns);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public static function getRoots(array $columns = ['*'])
    {
        return self::onlyRoot()->get($columns);
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
