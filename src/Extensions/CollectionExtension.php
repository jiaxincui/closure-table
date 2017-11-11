<?php

namespace Jiaxincui\ClosureTable\Extensions;

use Illuminate\Database\Eloquent\Collection;

class CollectionExtension extends Collection
{
    public function toTree($key = 'id', $parent = 'parent', $children = 'children')
    {
        $data = $this->toArray();
        $items = array();
        foreach ($data as $v) {
            $items[$v[$key]] = $v;
        }
        $tree = array();
        foreach ($items as $item) {
            if (isset($items[$item[$parent]])) {
                $items[$item[$parent]][$children][] = &$items[$item[$key]];
            } else {
                $tree[] = &$items[$item[$key]];
            }
        }
        return $tree;
    }
}