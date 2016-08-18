<?php

namespace FreeSwitch;

/**
 * Key-values pairs from a FS event
 * @author Daniel Finke <danielfinke2011@gmail.com>
 */
class FreeSwitchEvent implements \ArrayAccess {
    public $fields;

    /**
     * Create a new event instance from a plain text body
     * @param string $text A plain text body with lines like "Key: Value\n"
     *     or "Value\n"
     * @return FreeSwitchEvent The event instance with field data
     */
    public static function createFromPlainText($text) {
        $event = new FreeSwitchEvent();

        $lines = explode("\n", $text);
        foreach($lines as $line) {
            $lineParts = explode(':', $line);

            // Not a key-value pair
            if(count($lineParts) == 1) {
                $key = $lineParts[0];
                $value = true;
            }
            else {
                $key = $lineParts[0];
                // May be more than one colon, only first counts for KV
                $value = '';
                for($i = 1; $i < count($lineParts); $i++) {
                    $value .= $lineParts[$i];
                }
            }

            if(!empty($key)) {
                $event[trim($key)] = trim(urldecode($value));
            }
        }

        return $event;
    }

    /**
     * Create an empty event instance
     */
    public function __construct() {
        $this->fields = array();
    }

    public function offsetExists($offset) {
        return array_key_exists($offset, $this->fields);
    }

    public function offsetGet($offset) {
        return $this->fields[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->fields[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->fields[$offset]);
    }
}

?>
