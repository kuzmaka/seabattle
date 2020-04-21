<?php


const MOVE = 'MOVE';
const SURFACE = 'SURFACE';
const TORPEDO = 'TORPEDO';
const SILENCE = 'SILENCE';
const SONAR = 'SONAR';
const MINE = 'MINE';
const TRIGGER = 'TRIGGER';
const ALL_ACTION_NAMES = [MOVE, SURFACE, TORPEDO, SILENCE, SONAR, MINE, TRIGGER];

foreach (genActionOrders([MOVE, SURFACE, TORPEDO]) as $actionOrder) {
    echo implode(' ', $actionOrder), "\n";
}

function availableActionOrders($prevActionOrder = [])
{
    foreach (allActions() as $action) {
        if (!in_array($action, $prevActionOrder)) {
            $actionOrder = $prevActionOrder;
            $actionOrder[] = $action;
            yield $actionOrder;
            yield from availableActionOrders($actionOrder);
        }
    }
}

function allActions()
{
    return ['MOVE', 'SURFACE', 'TORPEDO'];
}


function genActionOrders($actionNames, $actionOrder = [])
{
    foreach ($actionNames as $key => $actionName) {
        $nextActionOrder = $actionOrder;
        $nextActionOrder[] = $actionName;
        yield $nextActionOrder;

        $nextActionNames = $actionNames;
        unset($nextActionNames[$key]);
        yield from genActionOrders($nextActionNames, $nextActionOrder);
    }
}