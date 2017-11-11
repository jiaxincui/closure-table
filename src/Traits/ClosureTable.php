<?php
namespace Jiaxincui\ClosureTable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jiaxincui\ClosureTable\Exceptions\ClosureTableException;
use Jiaxincui\ClosureTable\Extensions\CollectionExtension;

trait ClosureTable
{
    protected $ancestorColumn = 'ancestor';
    protected $descendantColumn = 'descendant';
    protected $distanceColumn = 'distance';
    protected $parent_id;

    /**
     * Deleted Listener
     */
    public static function boot()
    {
        parent::boot();

        static::deleted(function (Model $model) {
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
        return $this->ancestorColumn;
    }

    /**
     * Get this closure table descendant column
     *
     * @return string
     */
    protected function getDescendantColumn()
    {
        return $this->descendantColumn;
    }

    /**
     * Get this closure table distance(hierarchy) column
     *
     * @return string
     */
    protected function getDistanceColumn()
    {
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
        return $this->parentColumn;
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
        $distance = $this->getDistanceColumn();

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
        $distance = $this->getDistanceColumn();
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
        $distance = $this->getDistanceColumn();

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
     * Result with parent column
     *
     * @return mixed
     */
    protected function joinClosureWithParent()
    {
        if (! $this->exists) throw new ModelNotFoundException();

        $key = $this->getKey();
        $keyN = $this->getKeyName();
        $closureTable = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $parentColumn = $this->getParentColumn();
        $distance = $this->getDistanceColumn();
        $keyName = $this->getQualifiedKeyName();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $query = $this
            ->join($closureTable, $descendant, '=', $keyName)
            ->where($ancestor, '=', $key)
            ->select(DB::raw("*, IFNULL((
                SELECT c.{$ancestorColumn} FROM {$closureTable} AS c 
                WHERE {$descendantColumn}={$keyN} 
                AND {$distance}=1
                ), 0) AS {$parentColumn}
                ")
            )
            ->where($distance,'>=', 0);
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
    protected function unbindRelationships()
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
    protected function associateTree($ancestorId = null)
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
     * Create a child from Array
     *
     * @param array $attributes
     * @return mixed
     * @throws ClosureTableException
     */
    public function createChild($attributes = [])
    {
        if ($this->joinRelationSelf()->count() === 0) throw new ClosureTableException('Model is not a node');

        $parent_id = $this->getKey();
        $child = $this->create($attributes);
        $this->insertClosure($parent_id, $child->id);
        return $child;
    }

    /**
     * Make this model to root
     *
     * @return bool
     */
    public function makeRoot()
    {
        return $this->insertSelfClosure() && $this->unbindRelationships();
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
    public function countAncestors()
    {
        return $this->joinRelationBy('ancestor')->count();
    }

    /**
     * @return bool
     */
    public function hasAncestors()
    {
        return !!$this->countAncestors();
    }

    /**
     * @return mixed
     */
    public function countDescendants()
    {
        return $this->joinRelationBy('descendant')->count();
    }

    /**
     * @return bool
     */
    public function hasDescendants()
    {
        return !!$this->countDescendants();
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
     * @param $ancestor
     * @return bool
     * @throws ClosureTableException
     */
    public function moveTo($ancestor)
    {
        $ancestorId = $this->parameter2Model($ancestor)->getKey();
        $ids = $this->getDescendantsAndSelf([$this->getKeyName()])->pluck($this->getKeyName())->toArray();

        if (in_array($ancestorId, $ids)) {
            throw new ClosureTableException('Can\'t move to descendant');
        }
        DB::connection($this->connection)->transaction(function () use ($ancestorId) {
            if ($this->joinRelationSelf()->count() > 0) {
                if (! $this->unbindRelationships()) {
                    throw new ClosureTableException('Unbind relationships failed');
                }
            }
            if (! $this->associateTree($ancestorId)) {
                throw new ClosureTableException('Associate tree failed');
            }
        });
        return true;
    }

    /**
     * This model and descendants to tree
     *
     * @param null $sortKey
     * @param string $mode
     * @return mixed
     */
    public function getTree($sortKey = null, $mode = 'asc')
    {
        if (! is_null($sortKey)) {
            return $this->joinClosureWithParent()->orderBy($sortKey, $mode)->get()->toTree();
        }
        return $this->joinClosureWithParent()->get()->toTree();
    }

    /**
     * @return mixed
     */
    public function queryDescendantsWithParent()
    {
        return $this->joinClosureWithParent();
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getDescendantsWithParent(array $columns = ['*'])
    {
        return $this->queryDescendantsWithParent()->get($columns);
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
    public function countSiblings()
    {
        return $this->querySiblings()->count();
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
     * @return mixed
     */
    public function countSiblingsAndSelf()
    {
        return $this->querySiblingsAndSelf()->count();
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

        if (! $this->associateTree($parent->getKey())) {
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
     * @return mixed
     */
    public static function scopeIsolated()
    {
        $instance = new static;
        return $instance->joinWithoutClosure();
    }

    /**
     * Get isolated item
     *
     * @param array $columns
     * @return mixed
     */
    public static function getIsolated(array $columns = ['*'])
    {
        return self::scopeIsolated()->get($columns);
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
     * @param array $models
     * @return CollectionExtension
     */
    public function newCollection(array $models = [])
    {
        return new CollectionExtension($models);
    }

}
