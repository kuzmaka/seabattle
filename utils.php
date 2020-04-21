<?php

require_once 'Player.php';
require_once 'Tracker.php';
require_once 'Map.php';
require_once 'Cell.php';
require_once 'Ship.php';

fclose(fopen('test.out', 'wb'));
ini_set('error_log', 'test.out');
error_log('log start');
