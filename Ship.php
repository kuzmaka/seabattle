<?php

require_once 'Cell.php';
require_once 'Path.php';

class Ship
{
    public $cell;
    public $path;
    public $oldPaths = [];
    public $mines = [];

    public function __construct(Cell $cell)
    {
        $this->cell = $cell;
        $this->path = new Path([$cell]);
    }

    public function __clone()
    {
        $this->path = clone $this->path;
    }

    public function visited(Cell $cell)
    {
        return $this->path->has($cell);
    }

    public function canMove($dir)
    {
        $cell = $this->cell->next($dir);
        return $cell && !$this->path->has($cell);
    }

    public function move($dir)
    {
        $this->cell = $this->cell->next($dir);
        $this->path->set($this->cell);
    }

    public function canSilence($dir, $r)
    {
        $cell = $this->cell;
        for ($i = 0; $i < $r; $i++) {
            $cell = $cell->next($dir);
            if (!$cell || $this->path->has($cell)) {
                return false;
            }
        }
        return true;
    }

    public function silence($dir, $r)
    {
        for ($i = 0; $i < $r; $i++) {
            $this->move($dir);
        }
    }

    public function inSector($sector)
    {
        return $this->cell->sector == $sector;
    }

    public function surface()
    {
        $this->oldPaths[] = $this->path;
        $this->path = new Path([$this->cell]);
    }

    public function inPaths(Cell $cell)
    {
        if ($this->path->has($cell)) {
            return true;
        }
        foreach ($this->oldPaths as $path) {
            if ($path->has($cell)) {
                return true;
            }
        }
        return false;
    }

    public function reachable()
    {
        $reachable = [$this->cell];
        $queue = [[$this->cell, 0]];
        while ($queue) {
            [$cell, $dist] = array_shift($queue);
            foreach (Map::DIRS as $dir) {
                $next = $cell->next($dir);
                if ($next && !in_array($next, $reachable, true) && !$this->visited($next)) {
                    $reachable[] = $next;
//                    if ($dist < $distance - 1) {
                        $queue[] = [$next, $dist + 1];
//                    }
                }
            }
        }
        return $reachable;
    }

    public function mine($dir)
    {
        $this->mines[] = $this->cell->next($dir);
    }
}