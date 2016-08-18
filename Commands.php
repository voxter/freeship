<?php

/**
 * Shortcut functions for various FS commands
 * @author Daniel Finke <danielfinke2011@gmail.com>
 */
class Commands {
    /**
     * Send a uuid_send_info to FS to cause JSON SIP INFO payload
     * @param string $uuid UUID to receive the command
     * @param array $data Associative array to be turned into JSON for SIP INFO
     */
    public static function infoCommand($uuid, $data) {
        return 'api uuid_send_info '.$uuid.' '.json_encode($data);
    }

    /**
     * Send a uuid_broadcast to FS to play media on a call. Will include the
     * path of the media file in the channel variable "media_path"
     * @param string $uuid UUID to receive the command
     * @param string $mediaPath Path to the media file to be played
     */
    public static function playCommand($uuid, $mediaPath) {
        return 'api uuid_broadcast '.$uuid.' playback::{media_path='.$mediaPath.'}'.$mediaPath.' both';
    }

    /**
     * Send a uuid_hold to FS to put a call on hold/take a call off hold
     * @param string $uuid UUID to receive the command
     * @param bool $enable True to put call on hold, false to take off hold
     */
    public static function holdCommand($uuid, $enable) {
        return 'api uuid_hold '.($enable ? 'on' : 'off').' '.$uuid;
    }

    /**
     * Send a uuid_broadcast to FS to say a string of digits on a call
     * @param string $uuid UUID to receive the command
     * @param string $digits String of digits to say on the call
     */
    public static function sayDigitsCommand($uuid, $digits) {
        return 'api uuid_broadcast '.$uuid.' say::en\sname_spelled\siterated\s'.$digits.' both';
    }

    /**
     * Send a uuid_kill to FS to hang up a call
     * @param string $uuid UUID to receive the command
     */
    public static function killCommand($uuid) {
        return 'api uuid_kill '.$uuid;
    }

    /**
     * Send a uuid_break to FS to cut off media playback on a call
     * @param string $uuid UUID to receive the command
     */
    public static function breakCommand($uuid) {
        return 'api uuid_break '.$uuid;
    }
}

?>
