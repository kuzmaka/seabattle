<? // To debug: error_log(var_export($var, true)); (equivalent to var_dump)

$dx = ['N' => 0, 'S' => 0, 'W' => -1, 'E' => 1];
$dy = ['N' => -1, 'S' => 1, 'W' => 0, 'E' => 0];

$opdir = ['N' => 'S', 'S' => 'N', 'W' => 'E', 'E' => 'W'];

fscanf(STDIN, "%d %d %d", $w, $h, $myId);
// error_log(var_export(compact('w', 'h', 'myId'), true));

$m = [];
$opMap = [];
$meMap = [];
for ($i = 0; $i < $h; $i++) {
    $s = stream_get_line(STDIN, $w + 1, "\n");
    $m[] = str_split($s);
    $opMap[] = str_split(strtr($s,'.','@'));
    $meMap[] = str_split(strtr($s,'.','@'));
}

// starting position
for (;;) {
    $x = mt_rand(5, 9);
    $y = mt_rand(5, 9);
    if (sea($m, $x, $y)) {
        visit($m, $x, $y);
        echo("$x $y\n");
        break;
    }
}

$dirs = ['N', 'S', 'W', 'E'];
shuffle($dirs);
$step = 0;
$mines = [];
$from = null;
$meFrom = null;
while (true) {
    $step++;
    fscanf(STDIN, "%d %d %d %d %d %d %d %d", $x, $y, $myLife, $oppLife, $torpedoCooldown, $sonarCooldown, $silenceCooldown, $mineCooldown);
    // error_log(var_export(compact('torpedoCooldown'), true));
    fscanf(STDIN, "%s", $sonarResult);
    // error_log(var_export(compact('sonarResult'), true));
    $opActions = explode('|', stream_get_line(STDIN, 200 + 1, "\n"));
    // error_log(var_export(compact('opAction', 'opParam1', 'opParam2'), true));

    if ($sonarResult === 'Y') {
        opSector($opMap, $sonarCheck);
    } elseif ($sonarResult === 'N') {
        opCleanSector($opMap, $sonarCheck);
    }

    foreach ($opActions as $opAction) {
        @list($opAction, $opParam1, $opParam2) = explode(' ', $opAction);
        if ($opAction === 'SURFACE') {
            opSector($opMap, $opParam1);
            $from = null;
        } elseif ($opAction === 'MOVE') {
            opMove($opMap, $opParam1);
            $from = $opParam1;
        } elseif ($opAction === 'SILENCE') {
            opSilence($opMap, $from);
            $from = null;
        } elseif ($opAction === 'TORPEDO') {
            opTorpedo($opMap, $opParam1, $opParam2);
            opHurt($opMap, $opParam1, $opParam2, $x, $y);
        }
    }

    $commands = [];

    // choose wider direction
    $d = find_wider_dir($m, $x, $y, $dirs);
    if ($d) {
        $x1 = $x + $dx[$d];
        $y1 = $y + $dy[$d];
        if (sea($m, $x1, $y1)) {
            visit($m, $x1, $y1);
            $last_visit = [$x1, $y1];
            $fire = false;
            $opPosCnt = opPosCount($opMap);
            $opSecs = opSectors($opMap);
            if ($torpedoCooldown === 0) {
                // fire!
                $fire = fire($m, $opMap, $x, $y);
                if ($fire) {
                    $commands[] = $fire;
                }
            }
            if ($sonarCooldown === 0) {
                if (count($opSecs) > 1) {
                    $sonarCheck = $opSecs[0];
                    $commands[] = "SONAR $sonarCheck";
                }
            }
            if ($mineCooldown === 0) {
                $mdirs = $dirs;
                shuffle($mdirs);
                foreach ($mdirs as $md) {
                    $mx = $x + $dx[$md];
                    $my = $y + $dy[$md];
                    if (sea_dirty($m, $mx, $my)) {
                        $commands[] = "MINE $md";
                        $mines["$mx $my"] = [$mx, $my];
                        break;
                    }
                }
            } else {
                if ($mines) {
                    $p = localizeTarget($opMap);
                    if ($p) {
                        list($good, $bad) = $p;
                        $g = coo_intersect($good, $mines);
                        if (!$g) {
                            $g = coo_intersect($bad, $mines);
                        }
                        if ($g) {
                            shuffle($g);
                            list($tx, $ty) = reset($g);
                            if (!($tx === $x && $ty === $y && $myLife <= $oppLife)
                                && !(abs($tx - $x) + abs($ty - $y) === 1 && $myLife <= 1)
                            ) {
                                $commands[] = "TRIGGER $tx $ty";
                                unset($mines["$tx $ty"]);
                            }
                        }
                    }
                }
            }
            if ($silenceCooldown > 0) {
                $commands[] = "MOVE $d SILENCE";
            } elseif ($mineCooldown > 0 && count($mines) < 7) {
                $commands[] = "MOVE $d MINE";
            } elseif ($torpedoCooldown > 0) {
                $commands[] = "MOVE $d TORPEDO";
            } else {
                $commands[] = "MOVE $d SONAR";
            }
            if ($torpedoCooldown === 0 && !$fire) {
                // fire!
                $fire = fire($m, $opMap, $x1, $y1);
                if ($fire) {
                    $commands[] = $fire;
                }
            }
        }
    }

    if (!$commands) {
        // not moved - need to clean visited cells
        clean($m);
        visit($m, $x, $y);
        $last_visit = [$x, $y];
        $commands[] = "SURFACE";
        shuffle($dirs);
    }

    // check my visibility and apply silence if necessary
    foreach ($commands as $cmd) {
        @list($act, $p1, $p2) = explode(' ', $cmd);
        if ($act === 'SURFACE') {
            opSector($meMap, sector($x, $y));
            $meFrom = null;
        } elseif ($act === 'MOVE') {
            opMove($meMap, $p1);
            $meFrom = $p1;
        } elseif ($act === 'TORPEDO') {
            opTorpedo($meMap, $p1, $p2);
        }
    }
    if (opPosCount($meMap) < 11) {
        if ($silenceCooldown === 0) {
            $d1 = find_wider_dir($m, $last_visit[0], $last_visit[1], $dirs);
            if ($d1) {
                $sx1 = $last_visit[0] + $dx[$d1];
                $sy1 = $last_visit[1] + $dy[$d1];
                visit($m, $sx1, $sy1);
                // $d2 = find_wider_dir($m, $sx1, $sy1, $dirs);
                // if ($d2) {
                // $sx2 = $sx1 + $dx[$d];
                // $sy2 = $sy1 + $dy[$d1];
                // visit($m, $sx2, $sy2);
                // $commands[] = "SILENCE $d2 2";
                // } else {
                $commands[] = "SILENCE $d1 1";
                // }
                opSilence($meMap, $meFrom);
            }
        }
    }
    $commands[] = "MSG " . opPosCount($meMap);
    show_map($meMap);

    echo implode('|', $commands), "\n";
}

function sector($x, $y)
{
    return floor($y / 5) * 3 + floor($x / 5) + 1;
}

function sea($m, $x, $y)
{
    return 0 <= $x && $x < 15 && 0 <= $y && $y < 15 && $m[$y][$x] === '.';
}

function sea_dirty($m, $x, $y)
{
    return 0 <= $x && $x < 15 && 0 <= $y && $y < 15 && $m[$y][$x] !== 'x';
}

function visit(&$m, $x, $y)
{
    $m[$y][$x] = '@';
}

function unvisit(&$m, $x, $y)
{
    $m[$y][$x] = '.';
}

function visited($m, $x, $y)
{
    return 0 <= $x && $x < 15 && 0 <= $y && $y < 15 && $m[$y][$x] === '@';
}

function clean(&$m)
{
    global $w, $h;
    foreach ($m as $i => $row) {
        foreach ($row as $j => $v) {
            if ($v === '@') {
                $m[$i][$j] = '.';
            }
        }
    }
}

function find_wider_dir($m, $x, $y, $dirs)
{
    global $dx, $dy;

    $max = -1;
    $argmax = false;
    foreach ($dirs as $d) {
        // error_log('trying '.$d);
        $x1 = $x + $dx[$d];
        $y1 = $y + $dy[$d];
        if (!sea($m, $x1, $y1)) {
            continue;
        }
        $v = fill($m, $x1, $y1);
        // error_log('result '.$v);
        if ($v > $max) {
            $max = $v;
            $argmax = $d;
        }
    }
    return $argmax;
}

function fill($m, $x, $y)
{
    global $dx, $dy;

    if (!sea($m, $x, $y)) {
        return 0;
    }
    $q = [[$x, $y]];
    $history = [[$x, $y]];
    $c = 0;
    $m1 = $m;
    while ($q) {
        $c++;
        if ($c > 30) break;
        list($x, $y) = array_shift($q);
        // error_log("shift $x $y");
        visit($m1, $x, $y);
        foreach (['N', 'S', 'W', 'E'] as $d) {
            $x1 = $x + $dx[$d];
            $y1 = $y + $dy[$d];
            if (sea($m1, $x1, $y1) && !in_array([$x1, $y1], $history)) {
                $q[] = [$x1, $y1];
                $history[] = [$x1, $y1];
            }
        }
    }
    return $c;
}

function show_map($map)
{
    global $w, $h;

    for ($y = 0; $y < 15; $y++) {
        $s = '';
        for ($x = 0; $x < 15; $x++) {
            $s.=$map[$y][$x];
            if ($x == 4 || $x == 9) {
                $s.='|';
            }
        }
        error_log("$s\n");
        if ($y == 4 || $y ==9) {
            error_log("-----+-----+-----\n");
        }
    }
}

function opSector(&$opMap, $sector)
{
    $y0 = 5 * floor(($sector - 1) / 3);
    $x0 = 5 * floor(($sector - 1) % 3);;
    for ($y = 0; $y < 15; $y++) {
        for ($x = 0; $x < 15; $x++) {
            if ($y0 <= $y && $y < $y0 + 5 && $x0 <= $x && $x < $x0 + 5) {
                continue;
            }
            if (visited($opMap, $x, $y)) {
                unvisit($opMap, $x, $y);
            }
        }
    }
}

function opCleanSector(&$opMap, $sector)
{
    $y0 = 5 * floor(($sector - 1) / 3);
    $x0 = 5 * floor(($sector - 1) % 3);;
    for ($y = $y0; $y < $y0 + 5; $y++) {
        for ($x = $x0; $x < $x0 + 5; $x++) {
            if (visited($opMap, $x, $y)) {
                unvisit($opMap, $x, $y);
            }
        }
    }
}

function opMove(&$opMap, $dir)
{
    global $h, $w, $dx, $dy;

    $newMap = $opMap;
    clean($newMap);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (visited($opMap, $x, $y)) {
                $x1 = $x + $dx[$dir];
                $y1 = $y + $dy[$dir];
                if (sea($newMap, $x1, $y1)) {
                    visit($newMap, $x1, $y1);
                }
            }
        }
    }
    $opMap = $newMap;
}

function opSilence(&$opMap, $from)
{
    global $h, $w, $dx, $dy, $opdir;

    $newMap = $opMap;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (visited($opMap, $x, $y)) {
                foreach (['N','S','W','E'] as $dir) {
                    if ($opdir[$dir] === $from) {
                        continue;
                    }
                    $x1 = $x;
                    $y1 = $y;
                    for ($i = 0; $i < 4; $i++) {
                        $x1 += $dx[$dir];
                        $y1 += $dy[$dir];
                        if (!sea($opMap, $x1, $y1)) {
                            break;
                        }
                        visit($newMap, $x1, $y1);
                    }
                }
            }
        }
    }
    $opMap = $newMap;
}

function opTorpedo(&$opMap, $tx, $ty)
{
    global $h, $w, $dx, $dy;

    $q = [[$tx, $ty, 0]];
    $reached = [[$tx, $ty]];
    while ($q) {
        list($x, $y, $d) = array_shift($q);
        if ($d < 4) {
            foreach (['N','S','W','E'] as $dir) {
                if (sea_dirty($opMap, $x, $y)) {
                    $x1 = $x + $dx[$dir];
                    $y1 = $y + $dy[$dir];
                    $q[] = [$x1, $y1, $d + 1];
                    $reached[] = [$x1, $y1];
                }
            }
        }
    }

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (visited($opMap, $x, $y) && !in_array([$x, $y], $reached)) {
                unvisit($opMap, $x, $y);
            }
        }
    }
}

function opHurt(&$opMap, $tx, $ty, $x, $y)
{
    if ($tx === $x && $ty === $y) { // hurt = 2
        clean($opMap);
        visit($opMap, $x, $y);
    } elseif (abs($tx - $x) < 2 && abs($ty - $y) < 2) { // hurt = 1
        $newMap = $opMap;
        clean($newMap);
        for ($i = $tx - 1; $i <= $tx + 1; $i++) {
            for ($j = $ty - 1; $j <= $ty + 1; $j++) {
                if (visited($opMap, $i, $j)) {
                    visit($newMap, $i, $j);
                }
            }
        }
        $opMap = $newMap;
    } else {    // hurt = 0
        for ($i = $tx - 1; $i <= $tx + 1; $i++) {
            for ($j = $ty - 1; $j <= $ty + 1; $j++) {
                if (visited($opMap, $i, $j)) {
                    unvisit($opMap, $i, $j);
                }
            }
        }
    }
}

function opPosCount($opMap)
{
    global $h, $w, $dx, $dy;

    $c = 0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (visited($opMap, $x, $y)) {
                $c++;
            }
        }
    }
    return $c;
}

function opSectors($opMap)
{
    $secs = [];
    for ($sec = 1; $sec <= 9; $sec++) {
        $y0 = 5 * floor(($sec - 1) / 3);
        $x0 = 5 * floor(($sec - 1) % 3);
        $c = 0;
        for ($y = $y0; $y < $y0 + 5; $y++) {
            for ($x = $x0; $x < $x0 + 5; $x++) {
                if (visited($opMap, $x, $y)) {
                    $c++;
                }
            }
        }
        if ($c > 0) {
            $secs[$sec] = $c;
        }
    }
    arsort($secs);
    return array_keys($secs);
}

function fire($map, $opMap, $x, $y)
{
    global $myLife, $oppLife;
    global $h, $w, $dx, $dy;

    // where need to shoot
    $p = localizeTarget($opMap);
    if (!$p) {
        return false;
    }
    list($good, $bad) = $p;
    // error_log(var_export(compact('good', 'bad'), 1));

    // where torpedo can reach
    $q = [[$x, $y, 0]];
    $reached = [[$x, $y]];
    while ($q) {
        list($x, $y, $d) = array_shift($q);
        if ($d < 4) {
            foreach (['N','S','W','E'] as $dir) {
                if (sea_dirty($map, $x, $y)) {
                    $x1 = $x + $dx[$dir];
                    $y1 = $y + $dy[$dir];
                    if (!in_array([$x1, $y1], $reached)) {
                        $q[] = [$x1, $y1, $d + 1];
                        $reached[] = [$x1, $y1];
                    }
                }
            }
        }
    }

    $g = coo_intersect($good, $reached);
    if (!$g) {
        $g = coo_intersect($bad, $reached);
        if (!$g) {
            error_log("not reached");
            return false;
        }
    }
    shuffle($g);
    list($tx, $ty) = reset($g);
    if ($tx === $x && $ty === $y && $myLife <= $oppLife) {
        return false;
    }
    if (abs($tx - $x) + abs($ty - $y) === 1 && $myLife <= 1) {
        return false;
    }
    return "TORPEDO $tx $ty";

    $targets = [];
    for ($du = -4; $du <= 4; $du++) {
        for ($dv = -4; $dv <= 4; $dv++) {
            $u = $x + $du;
            $v = $y + $dv;
            // $m1 = $m;
            // clean($m1);
            if (abs($du) + abs($dv) <= 4
                && ($myLife > $oppLife || $myLife <= $oppLife && (abs($du) > 1 || abs($dv) > 1))
                && sea_dirty($map, $u, $v)
                && visited($opMap, $u, $v)
            ) {
                $targets[] = [$u, $v];
            }
        }
    }
    if ($targets) {
        shuffle($targets);
        list($u, $v) = $targets[0];
        return "TORPEDO $u $v";
        // return true;
    }
    return false;
}

function localizeTarget($opMap)
{
    $minX = 16;
    $maxX = -1;
    $minY = 16;
    $maxY = -1;
    for ($y = 0; $y < 15; $y++) {
        for ($x = 0; $x < 15; $x++) {
            if (visited($opMap, $x, $y)) {
                $minX = min($x, $minX);
                $maxX = max($x, $maxX);
                $minY = min($y, $minY);
                $maxY = max($y, $maxY);
            }
        }
    }
    if ($maxX - $minX < 3 && $maxY - $minY < 3) {
        $minI = $minJ = -1;
        $maxI = $maxJ = 1;
        if ($maxX - $minX == 2) {
            $maxX = $minX = ($maxX + $minX) / 2;
            $minI = $maxI = 0;
        }
        if ($maxY - $minY == 2) {
            $maxY = $minY = ($maxY + $minY) / 2;
            $minJ = $maxJ = 0;
        }
        if ($maxX - $minX == 1) {
            $minI = $maxI = 0;
        }
        if ($maxY - $minY == 1) {
            $minJ = $maxJ = 0;
        }
        $b = [];
        $good = [];
        for ($y = $minY; $y <= $maxY; $y++) {
            for ($x = $minX; $x <= $maxX; $x++) {
                if (visited($opMap, $x, $y)) {
                    $good[] = [$x, $y];
                }
                $a = [];
                for ($i = $minI; $i <= $maxI; $i++) {
                    for ($j = $minJ; $j <= $maxJ; $j++) {
                        if (sea_dirty($opMap, $x + $i, $y + $j)) {
                            $a[] = [$x + $i, $y + $j];
                        }
                    }
                }
                $b[] = $a;
            }
        }
        $c = coo_intersect(...$b);
        // error_log(var_export($c, 1));
        return [$good, $c];
    }

    return false;
}

function coo_intersect(...$cooss)
{
    $newCooss = [];
    foreach ($cooss as $coos) {
        $newCoos = [];
        foreach ($coos as $coo) {
            $newCoos[] = "$coo[0] $coo[1]";
        }
        $newCooss[] = $newCoos;
    }
    $res = count($newCooss) > 1 ? array_intersect(...$newCooss) : reset($newCooss);
    // error_log(var_export(compact('newCooss', 'res'), 1));
    $newRes = [];
    foreach ($res as $r) {
        $newRes[] = explode(' ', $r);
    }
    return $newRes;
}