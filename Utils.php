<?php

/**
 * Useful utility functions
 * @author Daniel Finke <danielfinke2011@gmail.com>
 */
class Utils {
    /**
     * Generate a 4 digit PIN of random digits 0-9
     * @return string The randomly generated PIN
     */
    public static function generatePIN() {
        $pin = '';
        for($i = 0; $i < 4; $i++) {
            $pin .= (string)rand(0, 9);
        }
        return $pin;
    }
}

?>
