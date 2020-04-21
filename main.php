<?php

require_once 'utils.php';


fscanf(STDIN, '%d %d %d', $w, $h, $myId);

// read map
for ($i = 0; $i < $h; $i++) {
    $s = stream_get_line(STDIN, $w + 1, "\n");
    $data[] = str_split($s);
}
$map = new Map($data);

// starting position
$maxLen = 0;
foreach ($map->cells as $cell) {
    $len = count($cell->reachable(225));
    if ($len > $maxLen) {
        $maxLen = $len;
        $maxCell = $cell;
    }
}
$myShip = new Ship($maxCell);
echo $myShip->cell->x, ' ', $myShip->cell->y, "\n";

$player = new Player($myShip);

$actions = '';
while (true) {
//    $startTime = microtime(true);

    fscanf(STDIN, '%d %d %d %d %d %d %d %d', $x, $y, $myLife, $opLife, $torpedoCooldown, $sonarCooldown,
        $silenceCooldown, $mineCooldown);
    fscanf(STDIN, '%s', $sonarResult);
    $opActions = stream_get_line(STDIN, 200 + 1, "\n");

    if ($sonarResult !== 'NA') {
        $player->opTracker->attackedBySonar($player->sonarSector, $sonarResult);
    }


    if (preg_match('/TORPEDO (\d+) (\d+)/', $opActions, $t)) {
        if (preg_match('/TRIGGER (\d+) (\d+)/', $opActions, $m)) {
            $player->opMeTracker->attackedByTorpedoAndMine($t[1], $t[2], $m[1], $m[2], $player->myLife - $myLife);
        } else {
            $player->opMeTracker->attackedByTorpedoOrMine($t[1], $t[2], $player->myLife - $myLife);
        }
    } else {
        if (preg_match('/TRIGGER (\d+) (\d+)/', $opActions, $m)) {
            $player->opMeTracker->attackedByTorpedoOrMine($m[1], $m[2], $player->myLife - $myLife);
        } else {
            // we don't know if he didn't hurt himself
            if (preg_match('/TORPEDO (\d+) (\d+)/', $actions, $t)) {
                if (preg_match('/TRIGGER (\d+) (\d+)/', $actions, $m)) {
                    $player->opTracker->attackedByTorpedoAndMine($t[1], $t[2], $m[1], $m[2], $player->opLife - $opLife);
                } else {
                    $player->opTracker->attackedByTorpedoOrMine($t[1], $t[2], $player->opLife - $opLife);
                }
            } else {
                if (preg_match('/TRIGGER (\d+) (\d+)/', $actions, $m)) {
                    $player->opTracker->attackedByTorpedoOrMine($m[1], $m[2], $player->opLife - $opLife);
                }
            }
        }
    }
//            error_log('Attack result:');
//        $player->opTracker->dump();
//    error_log('opMeTracker:');
//    $player->opMeTracker->dump();

    $player->opTracker->applyActions($opActions);
    error_log('Opp. map:');
    $player->opTracker->dump();

    $player = $player->go($myLife, $opLife, $torpedoCooldown, $sonarCooldown, $silenceCooldown, $mineCooldown);
    $actions = $player->formatActions();
    echo  "$actions\n";
    error_log('My actions: ' . $actions);
}
