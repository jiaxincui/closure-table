<?php

namespace Jiaxincui\ClosureTable\Extensions;

use Illuminate\Database\Eloquent\Collection;

class CollectionExtension extends Collection
{
    /**
     * @param string $primary
     * @param string $parent
     * @param string $children
     * @return array
     */
    public function toTree($primary = 'id', $parent = 'parent', $children = 'children')
    {
        $data = $this->toArray();
        if (! isset($data[0][$parent])) {
            return [];
        }
        $items = array();
        foreach ($data as $v) {
            $items[$v[$primary]] = $v;
        }

        $tree = array();
        foreach ($items as $item) {
            if (isset($items[$item[$parent]])) {
                $items[$item[$parent]][$children][] = &$items[$item[$primary]];
            } else {
                $tree[] = &$items[$item[$primary]];
            }
        }
        return $tree;
    }

    public function asTree($primary = 'id', $parent = 'parent', $children = 'children')
    {
        $childArr = [];
        $childIds = [];
        $items = $this->keyBy($primary);
        $items->each(function ($item, $key) use(&$childArr, &$childIds, $items, $primary, $parent) {
            if (isset($items[$item->$parent])) {
                $childArr[$item->$parent][$item->$primary] = $items[$item->$primary];
                array_push($childIds, $item->$primary);
            }
        });

        $tree = $items->map(function ($item, $key) use($childArr, $items, $primary, $children) {
            if (isset($childArr[$item->$primary])) {
                $item[$children] = $childArr[$item->$primary];
            };
            return $item;
        });

        foreach ($childIds as $id) {
            unset($tree[$id]);
        }

        return $tree;
    }

}