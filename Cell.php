<?php

class Cell
{
    public $map;
    public $x;
    public $y;
    public $sector;

    private $_next;

    public function __construct(Map $map, $x, $y)
    {
        $this->map = $map;
        $this->x = $x;
        $this->y = $y;
        $this->sector = 3 * floor($this->y / 5) + floor($this->x / 5) + 1;
    }

    public function initNext()
    {
        foreach (Map::DIRS as $dir) {
            $this->_next[$dir] = $this->_next($dir);
        }
    }

    private function _next($dir)
    {
        $x = $this->x + ['N' => 0, 'S' => 0, 'W' => -1, 'E' => 1][$dir];
        $y = $this->y + ['N' => -1, 'S' => 1, 'W' => 0, 'E' => 0][$dir];
        return $this->map->cellAt($x, $y);
    }

    public function next($dir)
    {
        return $this->_next[$dir];
    }

    public function nextCells()
    {
        $cells = [];
        foreach (Map::DIRS as $dir) {
            if ($cell = $this->_next[$dir]) {
                $cells[] = $cell;
            }
        }
        return $cells;
    }

    public function reachable($distance)
    {
        $reachable = [$this];
        $queue = [[$this, 0]];
        while ($queue) {
            [$cell, $dist] = array_shift($queue);
            foreach (Map::DIRS as $dir) {
                $next = $cell->next($dir);
                if ($next && !in_array($next, $reachable, true)) {
                    $reachable[] = $next;
                    if ($dist < $distance - 1) {
                        $queue[] = [$next, $dist + 1];
                    }
                }
            }
        }
        return $reachable;
    }

    public function around()
    {
        $cells = [];
        for ($x = $this->x - 1; $x <= $this->x + 1; $x++) {
            for ($y = $this->y - 1; $y <= $this->y + 1; $y++) {
                if ($x == $this->x && $y == $this->y) {
                    continue;
                }
                if ($cell = $this->map->cellAt($x, $y)) {
                    $cells[] = $cell;
                }
            }
        }
        return $cells;
    }
}