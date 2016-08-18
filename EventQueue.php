<?php

/**
 * Wrapper class for synchronized access to an event queue
 * @author Daniel Finke <danielfinke2011@gmail.com>
 */
class EventQueue {
    private $queue;

    /**
     * Create a new event queue with thread-safe access
     */
    public function __construct() {
        $this->queue = new Threaded();
    }

    /**
     * Add an event to the event queue
     * @param mixed $event The event to add to the queue
     */
    public function addEvent($event) {
        $this->queue[] = $event;
    }

    /**
     * Return true if the event queue has events waiting
     * @return bool True if the event queue has events waiting
     */
    public function hasNextEvent() {
        return $this->queue->count() != 0;
    }

    /**
     * Dequeue the next event from the queue, returning it
     * @return mixed The next event from the queue
     */
    public function getNextEvent() {
        return $this->queue->shift();
    }
}

?>
