<?php

namespace FreeSwitch;

require_once('FreeSwitch/FreeSwitchEvent.php');

interface FreeSwitchEventListenerCallbackInterface {
    function handleEvent($event);
}

class FreeSwitchEventListener extends \Thread {
    const READ_MAX = 4096;

    private $host;
    private $port;
    private $password;
    private $callback;
    private $socket;
    private $shouldStop = false;

    public function __construct($config, $callback) {
        if(isset($config['host'])) $this->host = $config['host'];
        if(isset($config['port'])) $this->port = $config['port'];
        if(isset($config['password'])) $this->password = $config['password'];

        $this->callback = $callback;

        if(!isset($config['autoConnect']) || $config['autoConnect']) {
            $this->connect();
        }
    }

    public function run() {
        $data = '';
        $newlines = 0;

        while(!$this->shouldStop) {
            $line = stream_get_line($this->socket, self::READ_MAX, "\n");

            if(strlen($line) > 0) {
                $data .= $line."\n";
            }
            else if($data != '' && strlen($line) == 0) {
                $newlines++;
            }

            if($data != '' && $newlines == 2) {
                // $this->$callback->handleEvent(FreeSwitchEvent::createFromPlainText($data));
                $this->callback->addEvent(FreeSwitchEvent::createFromPlainText($data));
                $data = '';
                $newlines = 0;
            }
        }
    }

    public function stop() {
        $this->synchronized(function($this) {
            $this->shouldStop = true;
        }, $this);
    }

    public function connect() {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr);
        socket_set_blocking($this->socket, false);
    }

    public function register() {
        // Authenticate
        $this->sendCommand('auth '.$this->password);

        // Start receiving events
        $cmd = 'event plain all';
        echo "Sending '$cmd' to FS to start event consumption\n";
        $this->sendCommand($cmd);
    }

    /**
     * Send a command to FS without waiting for a reply
     * @param string $cmd The command to send to FS
     */
    public function sendCommand($cmd) {
        $this->synchronized(function($socket, $cmd) {
            fputs($socket, $cmd."\n\n");
        }, $this->socket, $cmd);
    }

    /**
     * Asynchronously send a command to FS built from a set of KV pairs
     * @param array $array The KV pairs to make a payload
     */
    public function sendMultiCommand($array) {
        $cmd = '';
        foreach($array as $key => $value) {
            if(is_numeric($key)) {
                $cmd .= $value."\n";
            }
            else {
                $cmd .= $key.': '.$value."\n";
            }
        }
        $cmd .= "\n";

        $this->synchronized(function($socket, $cmd) {
            fputs($socket, $cmd);
        }, $this->socket, $cmd);
    }
}

?>
