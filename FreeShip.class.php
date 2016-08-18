<?php

require_once('Grid.php');
require_once('Utils.php');
require_once('Commands.php');
require_once('TimeoutThread.php');

class FreeShip {
    private static $GRID_DIMENSIONS = 11;

    private $listener;
    private $eventQueue;

    private $playerCount = 0;
    private $playerIdSequence = 0;
    private $currentPlayerId;
    private $calls = array();
    private $players = array();
    private $grid;
    private $dtmf = array();
    private $timeoutThread;
    private $breakAfterMissile = 'off';

    public function __construct($listener, $eventQueue) {
        $this->listener = $listener;
        $this->eventQueue = $eventQueue;
        $this->grid = new Grid(self::$GRID_DIMENSIONS, self::$GRID_DIMENSIONS);
    }

    public function launch() {
        $this->listener->start();

        while(true) {
            if($this->eventQueue->hasNextEvent()) {
                $this->dispatch($this->eventQueue->getNextEvent());
            }
            usleep(50000);
        }
    }

    private function dispatch($event) {
        // Authorize with FS to start receiving events
        if(isset($event['Content-Type']) &&
           $event['Content-Type'] == 'auth/request') {
            $this->listener->register();
        }
        else if(isset($event['Event-Name'])) {
            $eventName = $event['Event-Name'];
            // echo $eventName."\n";

            // New player joined
            if($eventName == 'CHANNEL_CREATE') {
                $this->playerCount++;
                $playerId = $this->playerIdSequence++;
                $pin = Utils::generatePIN();
                $uuid = $event['Channel-Call-UUID'];

                $this->listener->sendCommand('api uuid_answer '.$uuid);

                echo "Player $playerId ($uuid) has joined with PIN $pin ($this->playerCount players connected)\n";

                // Save information for later lookups
                $this->calls[$uuid] = array(
                    'playerId' => $playerId,
                    'uuid' => $uuid
                );
                $this->players[$playerId] = $this->calls[$uuid];

                // Add some ships for the player
                $this->grid->placeShips($playerId);

                // Send player data to new player
                $this->listener->sendCommand('api uuid_setvar '.$uuid.' fs_send_unsupported_info true');
                $data = array(
                    'event' => 'login',
                    'player_id' => $playerId,
                    'lives' => $this->grid->getPlayerLife($playerId)
                );
                $this->listener->sendCommand(Commands::infoCommand($uuid, $data));
                $coords = $this->grid->getShipCoords($playerId);
                $data2 = array(
                    'event' => 'setup',
                    'grid' => $coords
                );
                $this->listener->sendCommand(Commands::infoCommand($uuid, $data2));

                // Welcome the player to the game
                $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/welcome.wav'));
                $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/youareplayernumber.wav'));
                $this->listener->sendCommand(Commands::sayDigitsCommand($uuid, $playerId));
                // $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/yourpinis.wav'));
                // $this->listener->sendCommand(Commands::sayDigitsCommand($uuid, $pin));

                // Check if the first attack prompt should be launched
                if($this->playerCount == 1) {
                    $this->sendWaitCommand($uuid);
                }
                else if($this->playerCount == 2) {
                    $nextPlayerId = $this->getNextPlayerId();
                    $this->sendNextTurnCommand($nextPlayerId);
                    echo "It is player $nextPlayerId's turn\n";
                }
            }
            // FreeShip custom events
            else if($eventName == 'CUSTOM') {
                if(isset($event['Command'])) {
                    // Next player turn chosen
                    if($event['Command'] == 'sendevent freeship::next_turn') {
                        $this->currentPlayerId = $event['Next-Turn-Player-Id'];
                        $uuid = $this->players[$this->currentPlayerId]['uuid'];

                        $this->dtmf = array();

                        // Play wait media/hold music to other players
                        foreach($this->players as $playerId => $playerData) {
                            if($playerId != $this->currentPlayerId) {
                                $this->listener->sendCommand(Commands::breakCommand($playerData['uuid']));
                                $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/waitforturn.wav'));
                                $this->listener->sendCommand(Commands::sayDigitsCommand($playerData['uuid'], $this->currentPlayerId));
                                $this->listener->sendCommand(Commands::holdCommand($playerData['uuid'], true));
                            }
                        }

                        // Request digits from the player whose turn it is
                        $this->listener->sendCommand(Commands::breakCommand($uuid));
                        $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/yourturn.wav'));

                        echo "Waiting for player $this->currentPlayerId to enter digits\n";
                    }
                    // Player did not enter digits fast enough
                    else if($event['Command'] == 'sendevent freeship::turn_timeout' &&
                            $event['Player-Id'] == $this->currentPlayerId &&
                            // Ignore timeouts if we're playing missile/hit media
                            count($this->dtmf) < 2) {
                        $playerId = $event['Player-Id'];
                        $uuid = $this->players[$playerId]['uuid'];

                        $this->listener->sendCommand(Commands::breakCommand($uuid));
                        $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/invaliddigits.wav'));

                        // Get next turn player id, send next_turn event for that person
                        $nextPlayerId = $this->getNextPlayerId();
                        $this->sendNextTurnCommand($nextPlayerId);
                        echo "Player $this->currentPlayerId took too long\n";
                    }
                }
            }
            // Playback stop
            else if($event['Event-Name'] == 'PLAYBACK_STOP') {
                if($this->currentPlayerId != null) {
                    $uuid = $this->players[$this->currentPlayerId]['uuid'];

                    // End of freeship/yourturn.wav is trigger to start timeout
                    if(isset($event['media_path']) &&
                       $event['media_path'] == 'freeship/yourturn.wav' &&
                       $event['Channel-Call-UUID'] == $uuid) {
                        // Start timeout tracking
                        $event = new \FreeSwitch\FreeSwitchEvent();
                        $event->fields = array(
                            'Event-Name' => 'CUSTOM',
                            'Command' => 'sendevent freeship::turn_timeout',
                            'Player-Id' => $this->currentPlayerId
                        );
                        $this->timeoutThread = new TimeoutThread($this->eventQueue, $event, 10);
                        $this->timeoutThread->start();

                        echo "Started DTMF timeout for $this->currentPlayerId\n";
                    }
                    else if(isset($event['media_path']) &&
                            ($this->breakAfterMissile == 'hit' && $event['media_path'] == 'freeship/youwerehit-2.wav' ||
                             $this->breakAfterMissile == 'miss' && $event['media_path'] == 'freeship/miss.wav') &&
                            $event['Channel-Call-UUID'] == $uuid) {
                        // Get next turn player id, send next_turn event for that person
                        $this->dtmf = array();
                        $this->breakAfterMissile = 'off';
                        $nextPlayerId = $this->getNextPlayerId();
                        $this->sendNextTurnCommand($nextPlayerId);
                    }
                }
                // Delay hang up for losers until media is done playing
                if(isset($event['media_path']) &&
                   $event['media_path'] == 'freeship/sunk-2.wav') {
                    $uuid = $event['Channel-Call-UUID'];
                    $this->listener->sendCommand(Commands::killCommand($uuid));
                }
            }
            // DTMF events
            else if($eventName == 'DTMF' &&
                    isset($event['Channel-Call-UUID']) &&
                    $event['Channel-Call-UUID'] == $this->players[$this->currentPlayerId]['uuid'] &&
                    count($this->dtmf) < 2) {
                $digit = $event['DTMF-Digit'];

                // Update * to be 10
                if($digit == '*') { $digit = 10; }

                if(!isset($this->dtmf['x'])) {
                    $this->dtmf['x'] = $digit;
                }
                else {
                    $this->dtmf['y'] = $digit;
                    $x = $this->dtmf['x'];
                    $y = $this->dtmf['y'];

                    // Missile launch effect
                    foreach($this->players as $playerId => $playerData) {
                        $this->listener->sendCommand(Commands::breakCommand($playerData['uuid']));
                        $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/missilelaunch.wav'));
                        $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/miss.wav'));
                    }
                    $this->breakAfterMissile = 'miss';

                    // SIP INFO missile data
                    $data = array(
                        'event' => 'missile',
                        'x' => $x,
                        'y' => $y,
                    );

                    $shotTile = $this->grid->shoot($x, $y);
                    if($shotTile != null) {
                        $shotPlayerId = $shotTile['playerId'];

                        echo "Player $this->currentPlayerId hit $shotPlayerId at ($x,$y)\n";

                        // Hit notification
                        foreach($this->players as $playerId => $playerData) {
                            $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/hit.wav'));
                            $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/youwerehit-1.wav'));
                            $this->listener->sendCommand(Commands::sayDigitsCommand($playerData['uuid'], $shotPlayerId));
                            $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/youwerehit-2.wav'));
                            $this->listener->sendCommand(Commands::sayDigitsCommand($playerData['uuid'], $this->currentPlayerId));
                        }
                        $this->breakAfterMissile = 'hit';

                        // Prepare to remove player from game
                        if($this->grid->getPlayerLife($shotPlayerId) == 0) {
                            echo "Player $shotPlayerId killed by $this->currentPlayerId\n";

                            $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/sunk-1.wav'));
                            $this->listener->sendCommand(Commands::sayDigitsCommand($playerData['uuid'], $this->currentPlayerId));
                            $this->listener->sendCommand(Commands::playCommand($playerData['uuid'], 'freeship/sunk-2.wav'));
                        }

                        // Send missile data
                        $data['result'] = 1;
                        $this->listener->sendCommand(Commands::infoCommand($event['Channel-Call-UUID'], $data));
                        $this->listener->sendCommand(Commands::infoCommand($this->players[$shotPlayerId]['uuid'], $data));
                    }
                    else {
                        echo "Player $this->currentPlayerId missed at ($x,$y)\n";

                        // Send missile data
                        $data['result'] = 0;
                        $this->listener->sendCommand(Commands::infoCommand($event['Channel-Call-UUID'], $data));
                    }
                }
            }
            // Player disconnected
            else if($eventName == 'CHANNEL_DESTROY' &&
                    array_key_exists($event['Channel-Call-UUID'], $this->calls)) {
                $this->playerCount--;
                $uuid = $event['Channel-Call-UUID'];
                $playerId = $this->calls[$uuid]['playerId'];

                echo "Player $playerId ($uuid) has left ($this->playerCount players connected)\n";

                // Pass along the next turn if it was the leaver's turn
                if($playerId == $this->currentPlayerId) {
                    if($this->playerCount > 1) {
                        // Get next turn player id, send next_turn event for that person
                        $nextPlayerId = $this->getNextPlayerId();
                        $this->sendNextTurnCommand($nextPlayerId);
                    }
                }

                $this->grid->removeShips($playerId);
                unset($this->players[$playerId]);
                unset($this->calls[$uuid]);

                // Pause the game if only one player is left
                if($this->playerCount == 1) {
                    $this->currentPlayerId = null;

                    $lastPlayerId = $this->getNextPlayerId();
                    $lastPlayerUuid = $this->players[$lastPlayerId]['uuid'];
                    echo "Pausing game for last remaining player $lastPlayerId ($lastPlayerUuid)\n";
                    $this->listener->sendCommand(Commands::breakCommand($lastPlayerUuid));
                    $this->listener->sendCommand(Commands::playCommand($lastPlayerUuid, 'freeship/waitingforplayers.wav'));
                    $this->listener->sendCommand(Commands::holdCommand($lastPlayerUuid, true));
                }
            }
        }
    }

    private function sendWaitCommand($uuid) {
        $this->listener->sendCommand(Commands::playCommand($uuid, 'freeship/waitingforplayers.wav'));
        $this->listener->sendCommand(Commands::holdCommand($uuid, true));
    }

    private function sendNextTurnCommand($nextPlayerId) {
        $this->listener->sendMultiCommand(array(
            'sendevent freeship::next_turn',
            'Event-Name' => 'CUSTOM',
            'Next-Turn-Player-Id' => $nextPlayerId
        ));
    }

    private function getNextPlayerId() {
        $playersArr = array_values($this->players);
        if($this->currentPlayerId == null) {
            return $playersArr[0]['playerId'];
        }

        for($i = 0; $i < count($playersArr); $i++) {
            if($playersArr[$i]['playerId'] == $this->currentPlayerId) {
                break;
            }
        }
        $i++;
        // Overflow to 0 for next player
        if($i == count($playersArr)) {
            $i = 0;
        }
        return $playersArr[$i]['playerId'];
    }
}

?>
