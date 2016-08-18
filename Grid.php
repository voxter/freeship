<?php

/**
 * Structure and methods around a grid of ships
 * @author Daniel Finke <danielfinke2011@gmail.com>
 */
class Grid {
    private static $SHIP_TYPES = array(
        'battleship' => 4,
        'destroyer' => 3,
        'patrol boat' => 2
    );

    private $grid;
    private $width;
    private $height;
    private $playerLife = array();

    /**
     * Create a new grid with the specified dimensions
     * @param int $width Width of the grid
     * @param int $height Height of the grid
     */
    public function __construct($width, $height) {
        $this->grid = array_fill(0, $width, array_fill(0, $height, null));
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Place new ships for a player on the grid in random positions
     * @param int $playerId The player whose ships will be placed
     */
    public function placeShips($playerId) {
        $this->playerLife[$playerId] = 0;
        foreach(self::$SHIP_TYPES as $shipType => $size) {
            $this->placeShip($playerId, $shipType, $size);
        }
    }

    public function getShipCoords($playerId) {
        $coords = array();
        for($x = 0; $x < $this->width; $x++) {
            for($y = 0; $y < $this->height; $y++) {
                if($this->grid[$x][$y] != null &&
                   $this->grid[$x][$y]['playerId'] == $playerId) {
                    $coords[] = array(
                        'x' => $x,
                        'y' => $y
                    );
                }
            }
        }
        return $coords;
    }

    public function shoot($x, $y) {
        $tile = $this->grid[$x][$y];
        if($tile != null) {
            $this->playerLife[$tile['playerId']]--;
            if($this->playerLife[$tile['playerId']] == 0) {
                unset($this->playerLife[$tile['playerId']]);
            }
        }
        $this->grid[$x][$y] = null;
        return $tile;
    }

    public function getPlayerLife($playerId) {
        if(!isset($this->playerLife[$playerId])) { return 0; }
        return $this->playerLife[$playerId];
    }

    public function removeShips($playerId) {
        for($x = 0; $x < count($this->grid); $x++) {
            for($y = 0; $y < count($this->grid[$x]); $y++) {
                if($this->grid[$x][$y]['playerId'] == $playerId) {
                    $this->grid[$x][$y] = null;
                }
            }
        }
        unset($this->playerLife[$playerId]);
    }

    public function printGrid() {
        print_r($this->grid);
    }

    /**
     * Place a new ship for a player on the grid in a random position
     * @param int $playerId The player whose ship will be placed
     * @param string $shipType The type of ship to be placed
     * @param int $size The number of tiles for the ship to take up
     */
    private function placeShip($playerId, $shipType, $size) {
        $maxTries = 10;
        $tries = 0;

        // If no opening in 10 random placements, player is unlucky. No ship.
        while($tries < $maxTries) {
            $tries++;

            $coords = $this->buildShip($shipType, $size);
            // If ship overlaps something, try again
            if(!$this->isAvailable($coords)) {
                echo "Invalid ship placement for $playerId:$shipType\n";
                continue;
            }

            // Valid ship overwrites grid
            echo "<Player Id> $playerId <Ship Type> $shipType [";
            $i = 0;
            $count = count($coords);
            foreach($coords as $pair) {
                $x = $pair['x'];
                $y = $pair['y'];
                echo "($x,$y)";

                $this->grid[$x][$y] = array(
                    'playerId' => $playerId,
                    'shipType' => $shipType
                );

                $i++;
            }
            echo "]\n";

            $this->playerLife[$playerId] += $size;

            break;
        }
    }

    /**
     * Generate coordinates for a ship within grid bounds
     * @param string $shipType The type of ship to generate coords for
     * @param int $size The number of tiles for the ship to take up
     * @return array Coordinates array of x/y pairs
     */
    private function buildShip($shipType, $size) {
        $coords = array();

        // Pick coordinates depending on orientation
        $orientation = rand(0, 1);
        switch($orientation) {
            // Horizontal
            case 0:
                do {
                    $x = rand(0, $this->width-1);
                } while($x + $size > $this->width);
                $y = rand(0, $this->height-1);

                for($i = 0; $i < $size; $i++) {
                    $coords[] = array(
                        'x' => $x + $i,
                        'y' => $y
                    );
                }
                break;
            // Vertical
            case 1:
                $x = rand(0, $this->width-1);
                do {
                    $y = rand(0, $this->height-1);
                } while($y + $size > $this->height);

                for($i = 0; $i < $size; $i++) {
                    $coords[] = array(
                        'x' => $x,
                        'y' => $y + $i
                    );
                }
                break;
        }

        return $coords;
    }

    /**
     * Checks if a set of coordinates are available for placement in the grid
     * @param array $coords Coordinates array of x/y pairs
     * @return bool True if there is space for the coordinate pais, or false
     */
    private function isAvailable($coords) {
        foreach($coords as $pair) {
            $x = $pair['x'];
            $y = $pair['y'];
            if($this->grid[$x][$y] != null) {
                return false;
            }
        }
        return true;
    }
}

?>
