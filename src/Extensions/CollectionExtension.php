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
    public function toTree(string $primary = 'id', string $parent = 'parent', string $children = 'children'): array
    {
        $data = $this->toArray();
        $items = array();
        if (empty($data)) {
            return $items;
        }
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
}