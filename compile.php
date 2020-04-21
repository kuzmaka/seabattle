<?php


append_file('main.php');
append_file('Map.php');
append_file('Player.php');
append_file('Tracker.php');
append_file('Cell.php');
append_file('Path.php');
append_file('Ship.php');

function append_file($file)
{
    static $first = true;

    $s = file_get_contents($file);
    $s = preg_replace('/^require_once.*$/m', '', $s);
    if (!$first) {
        echo "\n\n////////////////// $file ////////////////////\n\n";
        $s = preg_replace('/^<\?(php)?/', '', $s, 1);
    }
    echo $s;

    $first = false;
}