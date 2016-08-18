<?php

require_once('EventQueue.php');
require_once('TimeoutThread.php');

$queue = new Threaded();
$eventQueue = new EventQueue($queue);

$eventQueue->addEvent(array('hello'));
$thread = new TimeoutThread($eventQueue, 5, array('timeout'));
$thread->start();

while(true) {
    if($eventQueue->hasNextEvent()) {
        $event = $eventQueue->getNextEvent();
        print_r($event)."\n";
        $eventQueue->addEvent($event);
    }
    sleep(1);
}

?>
