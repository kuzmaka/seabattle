<?php

class Player
{
    const MOVE = 'MOVE';
    const SURFACE = 'SURFACE';
    const TORPEDO = 'TORPEDO';
    const SILENCE = 'SILENCE';
    const SONAR = 'SONAR';
    const MINE = 'MINE';
    const TRIGGER = 'TRIGGER';
    
    const MAX_TORPEDO_COOLDOWN = 3;
    const MAX_SONAR_COOLDOWN = 4;
    const MAX_SILENCE_COOLDOWN = 6;
    const MAX_MINE_COOLDOWN = 3;

    public $myLife = 6;
    public $opLife = 6;
    public $torpedoCooldown = self::MAX_TORPEDO_COOLDOWN;
    public $sonarCooldown = self::MAX_SONAR_COOLDOWN;
    public $silenceCooldown = self::MAX_SILENCE_COOLDOWN;
    public $mineCooldown = self::MAX_MINE_COOLDOWN;

    public $ship;
    public $opTracker;
    public $opMeTracker;

    public $actions = [];
    public $score = -999999;
    public $start;

    public $opDamage;
    public $myDamage;
    public $sonarSector;
    public $charged;

    public function __construct(Ship $ship)
    {
        $this->ship = $ship;
        $this->opTracker = new Tracker($ship->cell->map);
        $this->opMeTracker = new Tracker($ship->cell->map);
    }

    public function __clone()
    {
        $this->ship = clone $this->ship;
        $this->opTracker = clone $this->opTracker;
        $this->opMeTracker = clone $this->opMeTracker;
    }

    public function go($myLife, $opLife, $torpedoCooldown, $sonarCooldown, $silenceCooldown, $mineCooldown)
    {
        $this->myLife = $myLife;
        $this->opLife = $opLife;
        $this->torpedoCooldown = $torpedoCooldown;
        $this->sonarCooldown = $sonarCooldown;
        $this->silenceCooldown = $silenceCooldown;
        $this->mineCooldown = $mineCooldown;

        $this->opDamage = 0;
        $this->myDamage = 0;
        $this->charged = 0;

        $this->actions = [];
        $this->score = -999999;
        $this->start = microtime(true);
        $bestPlayer = $this->bestPlayer();
        return $bestPlayer;
    }

    public function bestPlayer($depth = 0)
    {
        $start = microtime(true);
        $bestPlayer = $this;
        foreach ($this->getNextPossibleActions() as $action) {
            $ghost = clone $this;
            $ghost->do($action);
            error_log('Trying: ' . $ghost->formatActions() . ' ' . $ghost->score);
            if ($ghost->score > $bestPlayer->score) {
//                error_log('Better actions: ' . trim($ghost->formatActions()) . ';  Score: ' . $ghost->score);
                $bestPlayer = $ghost;
            }

            $time = round((microtime(true) - $this->start) * 1000);
            if ($time > 45) {
                error_log('EXIT BY TIMEOUT ' . $time);
                return $bestPlayer;
            }
            $nextBestPlayer = $ghost->bestPlayer($depth + 1);
            if ($nextBestPlayer->score > $bestPlayer->score) {
                $bestPlayer = $nextBestPlayer;
            }
        }
        return $bestPlayer;
    }

    public function getNextPossibleActions()
    {
        $actions = [];

        $prevActionNames = array_map(function ($action) {
            return $action[0];
        }, $this->actions);

        //        if (!in_array(self::SONAR, $prevActionNames)) {
        if (!$prevActionNames) {
            if ($this->sonarCooldown == 0) {
                $sectorCounts = $this->opTracker->sectorCounts();
                $notEmptySectors = array_filter($sectorCounts);
                if (count($notEmptySectors) > 1) {
                    $max = 0;
                    foreach ($notEmptySectors as $sector => $count) {
                        if ($count > $max) {
                            $max = $count;
                            $maxSector = $sector;
                        }
                    }
                    return [[self::SONAR, $maxSector]];
                }
            }
        }

        $canMove = false;
        if (!in_array(self::MOVE, $prevActionNames)) {
            foreach (Map::DIRS as $dir) {
                if ($this->ship->canMove($dir)) {
                    $actions[] = [self::MOVE, $dir, $this->bestCharge()];
                    $canMove = true;
                }
            }
        }

        if (!in_array(self::SURFACE, $prevActionNames) && !in_array(self::MOVE, $prevActionNames) && !$canMove) {
            $actions[] = [self::SURFACE];
        }

        if (!in_array(self::TORPEDO, $prevActionNames)) {
            if ($this->torpedoCooldown == 0 && $this->opTracker->cellsCount() < 20) {
                $reachable = $this->ship->cell->reachable(4);
                $cell = $this->opTracker->maxDamageCell($this->ship->cell, $reachable);
                if ($cell) {
                    $actions[] = [self::TORPEDO, $cell->x, $cell->y];
                }
            }
        }

        if (!in_array(self::MINE, $prevActionNames)) {
            if ($this->mineCooldown == 0) {
                foreach (Map::DIRS as $dir) {
                    if ($nextCell = $this->ship->cell->next($dir)) {
                        if (!in_array($nextCell, $this->ship->mines, true)) {
                            $actions[] = [self::MINE, $dir];
                        }
                    }
                }
            }
        }

        if (!in_array(self::TRIGGER, $prevActionNames)) {
            if (count($this->ship->mines) > 0 && $this->opTracker->cellsCount() < 20) {
                $cell = $this->opTracker->maxDamageCell($this->ship->cell, $this->ship->mines);
                if ($cell) {
                    $actions[] = [self::TRIGGER, $cell->x, $cell->y];
                }
            }
        }

        if (!in_array(self::SILENCE, $prevActionNames)) {
            if ($this->silenceCooldown == 0 && $this->opMeTracker->cellsCount() < 10) {
//                $actions[] = [self::SILENCE, 'N', 0];
                foreach (Map::DIRS as $dir) {
//                    for ($r = 1; $r <= 4; $r++) {
                    $r = 1;
                        if ($this->ship->canSilence($dir, $r)) {
                            $actions[] = [self::SILENCE, $dir, $r];
                        }
//                    }
                }
            }
        }

        return $actions;
    }

    public function bestCharge()
    {
        if ($this->torpedoCooldown > 0) {
            return self::TORPEDO;
        }
        if ($this->silenceCooldown > 0) {
            return self::SILENCE;
        }
        if ($this->mineCooldown > 0) {
            return self::MINE;
        }
        if ($this->sonarCooldown > 0) {
            return self::SONAR;
        }
        return self::MINE;
    }

    public function do($action)
    {
        @list($act, $p1, $p2) = $action;
        if ($act == self::MOVE) {
            $this->move($p1, $p2);
        } elseif ($act == self::SURFACE) {
            $this->surface();
        } elseif ($act == self::SILENCE) {
            $this->silence($p1, $p2);
        } elseif ($act == self::TORPEDO) {
            $this->torpedo($p1, $p2);
        } elseif ($act == self::MINE) {
            $this->mine($p1);
        } elseif ($act == self::TRIGGER) {
            $this->trigger($p1, $p2);
        } elseif ($act == self::SONAR) {
            $this->sonar($p1);
        }
        $this->actions[] = $action;
        $this->score = $this->score();
    }

    public function move($dir, $charge)
    {
        $this->ship->move($dir);
        $this->opMeTracker->move($dir);
        $this->charge($charge);
    }

    public function charge($charge)
    {
        if ($charge == self::TORPEDO) {
            $this->torpedoCooldown ?: $this->torpedoCooldown--;
        } elseif ($charge == self::SILENCE) {
            $this->silenceCooldown ?: $this->silenceCooldown--;
        } elseif ($charge == self::SONAR) {
            $this->sonarCooldown ?: $this->sonarCooldown--;
        } elseif ($charge == self::MINE) {
            $this->mineCooldown ?: $this->mineCooldown--;
        }
        $this->charged = 1;
    }

    public function surface()
    {
        $this->ship->surface();
        $this->opMeTracker->surface($this->ship->cell->sector);
    }

    public function silence($dir, $r)
    {
        $this->silenceCooldown = self::MAX_SILENCE_COOLDOWN;
        $this->ship->silence($dir, $r);
        $this->opMeTracker->silence();
    }

    public function torpedo($x, $y)
    {
        $this->torpedoCooldown = self::MAX_TORPEDO_COOLDOWN;

        // count damage
        $totalCount = $this->opTracker->cellsCount();

        $tCell = $this->ship->cell->map->cellAt($x, $y);
        $centerCount = $this->opTracker->shipsAtCell($tCell) ? 1 : 0;
        $nearCount = $this->opTracker->countCellsAround($tCell);
        $this->opDamage += (2 * $centerCount + $nearCount) / $totalCount;

        $myCell = $this->ship->cell;
        $myCenterCount = $myCell === $tCell ? 1 : 0;
        $myNearCount = 0;
        foreach ($tCell->around() as $taCell) {
            if ($taCell === $myCell) {
                $myNearCount++;
            }
        }
        $this->myDamage += 2 * $myCenterCount + $myNearCount;

        // reduce uncertainty
//        $checkedCount = $centerCount + $nearCount;
//        $this->opTorpedoWorstProbability = min($checkedCount / $totalCount, 1 - $checkedCount / $totalCount);

        $this->opMeTracker->torpedo($x, $y);
    }

    public function mine($dir)
    {
        $this->mineCooldown = self::MAX_MINE_COOLDOWN;

        $this->ship->mine($dir);
    }

    public function trigger($x, $y)
    {
        // count damage
        $totalCount = $this->opTracker->cellsCount();

        $tCell = $this->ship->cell->map->cellAt($x, $y);
        $centerCount = $this->opTracker->shipsAtCell($tCell) ? 1 : 0;
        $nearCount = $this->opTracker->countCellsAround($tCell);
        $this->opDamage += (2 * $centerCount + $nearCount) / $totalCount;

        $myCell = $this->ship->cell;
        $myCenterCount = $myCell === $tCell ? 1 : 0;
        $myNearCount = 0;
        foreach ($tCell->around() as $taCell) {
            if ($taCell === $myCell) {
                $myNearCount++;
            }
        }
        $this->myDamage += 2 * $myCenterCount + $myNearCount;

        // remove mine
        $this->ship->mines = array_filter($this->ship->mines, function ($mine) use ($tCell) {
            return $mine !== $tCell;
        });
    }

    public function sonar($sector)
    {
        $this->sonarCooldown = self::MAX_SONAR_COOLDOWN;

        $this->sonarSector = $sector;
        // reduce uncertainty
//        $totalCount = $this->opTracker->cellsCount();
//        $sectorCount = $this->opTracker->countCellsInSector($sector);
//        $this->opSonarWorstProbability = min($sectorCount / $totalCount, 1 - $sectorCount / $totalCount);
    }

    public function score()
    {
        $score =
            $this->myLife
            - $this->opLife
//            + (1 - $this->torpedoCooldown / 3) * 0.03
//            + (1 - $this->sonarCooldown / 4) * 0.04
//            + (1 - $this->silenceCooldown / 6) * 0.06
//            + (1 - $this->mineCooldown / 3) * 0.03
//            + $this->charged
//            + count($this->ship->reachable())
//            + 1 / $this->opTracker->cellsCount()
//            - 1 / $this->opMeTracker->cellsCount()
//            + $this->opDamage
//            - $this->myDamage
//            - $this->opTracker->minesDamage($this->ship->cell)
//            + count($this->ship->mines);
            + $this->charged / 3
            + 10 * count($this->ship->reachable()) / 225
            + 5 * 2 * 1 / $this->opTracker->cellsCount()
            - 7 * 2 * 1 / $this->opMeTracker->cellsCount()
            + 5 * $this->opDamage
            - 7 * $this->myDamage
            - 7 * $this->opTracker->minesDamage($this->ship->cell)
            + count($this->ship->mines);
        error_log(sprintf('score %f - %f + %f + %f + %f - %f + %f - %f - %f + %f',
            $this->myLife, $this->opLife,
            $this->charged / 3,
            10 * count($this->ship->reachable()) / 225,
            5 * 2 * 1 / $this->opTracker->cellsCount(),
            7 * 2 * 1 / $this->opMeTracker->cellsCount(),
            5 * $this->opDamage, 7 * $this->myDamage,
            7 * $this->opTracker->minesDamage($this->ship->cell),
            count($this->ship->mines)
        ));
        return $score;
    }

    public function formatActions()
    {
        $formattedActions = [];
        foreach ($this->actions as $action) {
            $formattedActions[] = implode(' ', $action);
        }
        return implode('|', $formattedActions);
    }
}