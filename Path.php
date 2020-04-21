<?php

require_once 'Cell.php';

class Path
{
    private $data = [0, 0, 0, 0];

    public function __construct($cells = [])
    {
        foreach ($cells as $cell) {
            $this->set($cell);
        }
    }

    public function set($cell)
    {
        $x = $cell->x;
        $y = $cell->y;
        $k = $y * 15 + $x;
        $i = floor($k / 64);
        $j = $k % 64;
        $this->data[$i] |= 1 << $j;
    }

    public function has($cell)
    {
        $x = $cell->x;
        $y = $cell->y;
        $k = $y * 15 + $x;
        $i = floor($k / 64);
        $j = $k % 64;
        return $this->data[$i] & (1 << $j);
    }
}