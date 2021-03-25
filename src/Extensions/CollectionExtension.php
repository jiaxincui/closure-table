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
        if (!key_exists($parent, $this->first()->toArray())) {
            return [];
        }
        $items = $this->mapWithKeys(function ($item) use ($primary) {
            return [$item[$primary] => $item];
        })->toArray();
        $tree = [];
        foreach ($items as $item) {
            if (isset($items[$item[$parent]])) {
                $items[$item[$parent]][$children][] = &$items[$item[$primary]];
            } else {
                $tree[] = &$items[$item[$primary]];
            }
        }
        return $tree;
    }
}