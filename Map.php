<?php

require_once 'Cell.php';

class Map
{
    const DIRS = ['N', 'S', 'W', 'E'];

    public $data;

    /**
     * @var Cell[]
     */
    public $cells;

    public function __construct($data)
    {
        $this->data = $data;
        foreach ($data as $y => $row) {
            foreach ($row as $x => $type) {
                if ($type === '.') {
                    $this->cells["$x $y"] = new Cell($this, $x, $y);
                }
            }
        }
        foreach ($this->cells as $cell) {
            $cell->initNext();
        }
    }

    public function cellAt($x, $y)
    {
        return $this->cells["$x $y"] ?? null;
    }
}