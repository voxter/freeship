--
-- FreeShip for ClueCon 2015!
--

package.path = package.path .. ";/usr/share/freeswitch/scripts/?.lua";
require "player";

-- Variables
local player_id = 0;
local player_pin = 0;
local session_id = 0;
local players = 0;

-- Connect to the database
local dbh = freeswitch.Dbh("odbc://freeship:postgres:postgres");
assert(dbh:connected()); -- exits the script if we didn't connect properly

-- Create API Object
api = freeswitch.API();

session_id = session:getVariable("uuid");

function get_players()
        local num = 0;

        dbh:query("SELECT COUNT(*) AS num FROM players WHERE active = 't'", function(row)
                num = tonumber(row.num);
        end);

        return num;
end

function get_next_player(player_id)
        local id = 0;

        dbh:query("SELECT player_id FROM players WHERE player_id > " .. player_id .. " AND active = 't' LIMIT 1", function(row)
                id = tonumber(row.player_id);
        end);

        if(id == 0) then
                dbh:query("SELECT MIN(player_id) AS min_player_id FROM players WHERE active = 't'", function(row)
                        id = tonumber(row.min_player_id);
                end);
        end

        return id;
end

-- Init
session:set_tts_params("flite", "kal");
session:setAutoHangup(false);
session:preAnswer();
session:sleep(100);

-- Hangup hook function
function hangup_hook(s, status, arg)
        freeswitch.consoleLog("debug", "[FREESHIP] Player ID " .. player_id .. " has disconnected");

        -- Remove player from the game
        dbh:query("UPDATE players SET active = 'f' WHERE player_id = " .. player_id);
end


-- Check for available player slot
if (get_players() > 9) then
        freeswitch.consoleLog("debug", "[FREESHIP] Game is full!");
        session:speak("The game is full, please try back later");

        session:hangup("USER_BUSY");
        return;
end

-- Answer
session:answer();

-- Set the hangup hook
session:setHangupHook("hangup_hook", player_id);


-- Get player ID & PIN
dbh:query("SELECT nextval('player_id_seq') AS id, nextval('player_pin_seq') AS pin", function(row)
        player_id = row.id;
        player_pin = row.pin;
end);


while (session:ready() == true) do
    freeswitch.consoleLog("debug", "[FREESHIP] handling player " .. player_id .. " for " .. session_id);
    session:execute("valet_park", "valet_lot " .. player_id);
    session:sleep(4000);
end

-- Return/hangup
session:hangup();
