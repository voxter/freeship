<?php

class TimeoutThread extends Thread {
    private static $SLEEP_TIME = 50000;

    private $queue;
    private $event;
    private $timeoutSeconds;

    private $startTime = 0;

    public function __construct($queue, $event, $timeoutSeconds) {
        $this->queue = $queue;
        $this->event = $event;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function run() {
        $this->startTime = time();
        while(true) {
            if(time() > $this->startTime + $this->timeoutSeconds) {
                $this->queue->addEvent($this->event);
                break;
            }
            usleep(self::$SLEEP_TIME);
        }
    }
}

?>
