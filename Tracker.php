<?php

require_once 'utils.php';

class Tracker
{
    public $map;

    /**
     * @var Ship[]
     */
    public $ships = [];

    public function __construct(Map $map)
    {
        $this->map = $map;
        foreach ($map->cells as $cell) {
            $this->ships[] = new Ship($cell);
        }
    }

    public function __clone()
    {
        foreach ($this->ships as &$ship) {
            $ship = clone $ship;
        }
    }

    public function filterShips($callback)
    {
        $this->ships = array_filter($this->ships, $callback);
    }

    public function applyActions($str)
    {
        foreach (explode('|', $str) as $actionStr) {
//            error_log("Action: $actionStr");
            @list($action, $param1, $param2) = explode(' ', $actionStr);
            if ($action === 'MOVE') {
                $this->move($param1);
            } elseif ($action === 'SILENCE') {
                $this->silence();
            } elseif ($action === 'SURFACE') {
                $this->surface($param1);
            } elseif ($action === 'TORPEDO') {
                $this->torpedo($param1, $param2);
            } elseif ($action === 'MINE') {
                $this->mine();
            } elseif ($action === 'TRIGGER') {
                $this->trigger($param1, $param2);
            }
//            $this->dump();
        }
    }

    public function move($dir)
    {
        $this->ships = array_filter($this->ships, function ($ship) use ($dir) {
            return $ship->canMove($dir);
        });
        foreach ($this->ships as $ship) {
            $ship->move($dir);
        }
    }

    public function silence()
    {
        $newShips = [];
        foreach ($this->ships as $ship) {
            foreach (Map::DIRS as $dir) {
                if ($ship->canMove($dir)) {
                    $newShip = clone $ship;
                    $newShip->move($dir);
                    $newShips[] = $newShip;
                    if ($newShip->canMove($dir)) {
                        $newShip = clone $newShip;
                        $newShip->move($dir);
                        $newShips[] = $newShip;
                        if ($newShip->canMove($dir)) {
                            $newShip = clone $newShip;
                            $newShip->move($dir);
                            $newShips[] = $newShip;
                            if ($newShip->canMove($dir)) {
                                $newShip = clone $newShip;
                                $newShip->move($dir);
                                $newShips[] = $newShip;
                            }
                        }
                    }
                }
//                for ($r = 1; $r <= 4; $r++) {
//                    if ($ship->canSilence($dir, $r)) {
//                        $newShip = clone $ship;
//                        $newShip->silence($dir, $r);
//                        $newShips[] = $newShip;
//                    }
//                }
            }
        }
        if ($newShips) {
            $this->ships = array_merge($this->ships, $newShips);
        }

        // clean ships in cells with many ships
//        foreach ($this->map->cells as $cell) {
//            $ships = $this->shipsAtCell($cell);
//            if (count($ships) > 4) {
//                $this->filterShips(function ($ship) use ($cell) {
//                    return $ship->cell !== $cell;
//                });
//                $this->ships[] = new Ship($cell);
//            }
//        }
    }

    public function surface($sector)
    {
        $this->ships = array_filter($this->ships, function ($ship) use ($sector) {
            if (!$ship->inSector($sector)) {
                return false;
            }
            $ship->surface();
            return true;
        });
    }

    public function torpedo($x, $y)
    {
        $reachable = $this->map->cellAt($x, $y)->reachable(4);
        $this->ships = array_filter($this->ships, function ($ship) use ($reachable) {
            return in_array($ship->cell, $reachable);
        });
    }

    public function mine()
    {
        foreach ($this->ships as $ship) {
            foreach (Map::DIRS as $dir) {
                if ($ship->cell->next($dir)) {
                    $ship->mine($dir);
                }
            }
        }
    }

    public function minesDamage(Cell $cell)
    {
        $around = $cell->around();
        $dmg = 0;
        foreach ($this->ships as $ship) {
            foreach ($ship->mines as $mine) {
                if ($mine === $cell) {
                    $dmg += 2;
                }
                if (in_array($mine, $around)) {
                    $dmg++;
                }
            }
        }
        return $dmg;
    }

    public function trigger($x, $y)
    {
//        $reachable = $this->map->cellAt($x, $y)->reachable(1);
//        $this->ships = array_filter($this->ships, function ($ship) use ($reachable) {
//            foreach ($reachable as $cell) {
//                if ($ship->inPaths($cell)) {
//                    return true;
//                }
//            }
//            return false;
//        });
    }

    public function cellsCount()
    {
        $used = [];
        foreach ($this->ships as $ship) {
            $x = $ship->cell->x;
            $y = $ship->cell->y;
            $used["$x $y"] = 1;
        }
        return count($used);
    }

    public function shipsAtCell($cell)
    {
        return array_filter($this->ships, function ($ship) use ($cell)  {
            return $ship->cell === $cell;
        });
    }

    public function maxDamageCell($center, $reachable)
    {
        $maxDmg = 0;
        $maxDmgCell = null;
        foreach ($reachable as $cell) {
            $dmg = 0;
            if ($cell === $center) {
                $dmg -= 2;
            }
            if ($this->shipsAtCell($cell)) {
                $dmg += 2;
            }
            foreach ($cell->around() as $aCell) {
                if ($this->shipsAtCell($aCell)) {
                    $dmg++;
                }
                if ($aCell === $center) {
                    $dmg--;
                }
            }
            if ($dmg > $maxDmg) {
                $maxDmg = $dmg;
                $maxDmgCell = $cell;
            }
        }
        return $maxDmgCell;
    }

    public function countCellsAround(Cell $cell)
    {
        $cnt = 0;
        foreach ($cell->around() as $aCell) {
            if ($this->shipsAtCell($aCell)) {
                $cnt++;
            }
        }
        return $cnt;
    }

    public function sectorCounts()
    {
        $sectors = array_fill(1, 15, 0);
        foreach ($this->ships as $ship) {
            $sectors[$ship->cell->sector]++;
        }
        return $sectors;
    }

    public function dump()
    {
        $out = $this->map->data;

        foreach ($this->ships as $ship) {
            $x = $ship->cell->x;
            $y = $ship->cell->y;
            if ($out[$y][$x] === '.') {
                $out[$y][$x] = 1;
            } elseif ($out[$y][$x] === 9) {
                $out[$y][$x] = '*';
            } else {
                $out[$y][$x]++;
            }
        }

//        $line = ' ';
//        for ($x = 0; $x < 15; $x++) {
//            if ($x == 5 || $x == 10) {
//                $line .= ' ';
//            }
//            $line .= $x % 10;
//        }
//        $line .= ' ';
//        error_log($line);
        for ($y = 0; $y < 15; $y++) {
            if ($y == 5 || $y == 10) {
                $line = '-----+-----+-----';
                error_log($line);
            }
            $line = '';
//            $line .= $y % 10;
            for ($x = 0; $x < 15; $x++) {
                if ($x == 5 || $x == 10) {
                    $line .= '|';
                }
                $line .= $out[$y][$x];
            }
//            $line .= $y % 10;
            error_log($line);
        }
//        $line = ' ';
//        for ($x = 0; $x < 15; $x++) {
//            if ($x == 5 || $x == 10) {
//                $line .= ' ';
//            }
//            $line .= $x % 10;
//        }
//        $line .= ' ';
//        error_log($line);
    }

    public function attackedByTorpedoOrMine($x, $y, $damage)
    {
        error_log("attackedByTorpedoOrMine $x $y *$damage*");
        $cell = $this->map->cellAt($x, $y);
        if ($damage == 2) {
            $this->filterShips(function ($ship) use ($cell) {
                return $ship->cell === $cell;
            });
        } elseif ($damage == 1) {
            $around = $cell->around();
            $this->filterShips(function ($ship) use ($around) {
                return in_array($ship->cell, $around, true);
            });
        } elseif ($damage == 0) {
            $around = $cell->around();
            $this->filterShips(function ($ship) use ($cell, $around) {
                return $ship->cell !== $cell && !in_array($ship->cell, $around, true);
            });
        }
    }

    public function attackedByTorpedoAndMine($xt, $yt, $xm, $ym, $damage)
    {
        $tCell = $this->map->cellAt($xt, $yt);
        $mCell = $this->map->cellAt($xm, $ym);
        if ($damage == 4) {
            $this->filterShips(function ($ship) use ($tCell) {
                return $ship->cell === $tCell;
            });
        } elseif (0 < $damage && $damage < 4) {
            $reachable = array_merge($tCell->reachable(1), $mCell->reachable(1));
            $this->filterShips(function ($ship) use ($reachable) {
                return in_array($ship->cell, $reachable, true);
            });
        } elseif ($damage == 0) {
            $reachable = array_merge($tCell->reachable(1), $mCell->reachable(1));
            $this->filterShips(function ($ship) use ($reachable) {
                return !in_array($ship->cell, $reachable, true);
            });
        }
    }

    public function attackedBySonar($sector, $result)
    {
        $this->filterShips(function (Ship $ship) use ($sector, $result) {
            return $result === 'Y' ? $ship->inSector($sector) : !$ship->inSector($sector);
        });
    }
}