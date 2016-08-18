<?php

/**
 * @author Daniel Finke <danielfinke2011@gmail.com>
 *
 * @todo getNextPlayerId occasionally failing in CHANNEL_DESTROY
 * @todo create TimeoutThread interface
 * @todo player id is being read digit-by-digit
 * @todo random hold music
 * @todo SIP INFO turn event
 * @todo timeout sound effect
 */

require_once('EventQueue.php');
require_once('FreeSwitch/FreeSwitchEventListener.php');
require_once('FreeShip.class.php');

$listenerConfig = array(
    'host' => 'freeship.voxter.com',
    'port' => 8021,
    'password' => 'fr33fr33ship'
);

$eventQueue = new EventQueue();
$listener = new \FreeSwitch\FreeSwitchEventListener($listenerConfig, $eventQueue);
$freeship = new FreeShip($listener, $eventQueue);
$freeship->launch();

?>

