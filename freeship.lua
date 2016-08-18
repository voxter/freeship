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

-- Hangup hook function
function hangup_hook(s, status, arg)
    freeswitch.consoleLog("debug", "[FREESHIP] Player ID " .. player_id .. " has disconnected");

    -- Remove player from the game
    dbh:query("UPDATE players SET active = 'f' WHERE player_id = " .. player_id);

    -- Remove player from map
    remove_ships(player_id);
end

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

-- Check for available player slot
if (get_players() > 9) then
    freeswitch.consoleLog("debug", "[FREESHIP] Game is full!");
    --session:speak("The game is full, please try back later");
    session:streamFile("freeship/gamefull.wav");

    session:hangup("USER_BUSY");
    return;
end

-- Get player ID & PIN
dbh:query("SELECT nextval('player_id_seq') AS id, nextval('player_pin_seq') AS pin", function(row) 
    player_id = row.id;
    player_pin = row.pin;
end);

-- Set X-vars
session:setVariable("sip_rh_X-Player-ID", player_id);
session:setVariable("sip_rh_X-Player-PIN", player_pin);

-- Answer
session:answer();

-- Set the hangup hook
session:setHangupHook("hangup_hook", player_id);

session_id = session:getVariable("uuid");
session:setVariable("fs_send_unsupported_info", "true");
api:executeString("uuid_send_info " .. session_id .. " {\"event\":\"login\",\"player_id\":" .. player_id .. ",\"score\":0,\"lives\":5}");

-- Place player ships
ship1 = place_ship(player_id, 0);
api:executeString("uuid_send_info " .. session_id .. " " .. ship1);
ship2 = place_ship(player_id, 1);
api:executeString("uuid_send_info " .. session_id .. " " .. ship2);

-- Provide player with ID & PIN
freeswitch.consoleLog("debug", "[FREESHIP] Player ID " .. player_id .. " has joined with PIN " .. player_pin);
session:streamFile("freeship/welcome.wav");
-- digits = session:playAndGetDigits(2, 5, 3, 3000, "#", "freeship/welcome.wav", "error.wav", "\\d+");

-- Insert player into game
dbh:query("INSERT INTO players VALUES (" .. player_id .. ", " .. player_pin .. ", '" .. session:getVariable("uuid") .. "', default, 't')");

--session:streamFile("freeship/youareplayernumber.wav");
--session:execute("say", "en name_spelled iterated " .. player_id);
--session:streamFile("freeship/yourpinis.wav");
--session:execute("say", "en name_spelled iterated " .. player_pin);
--session:streamFile("freeship/yourpinis.wav");
--session:execute("say", "en name_spelled iterated " .. player_pin);
session:sleep(100);

freeswitch.consoleLog("debug", "Number of players: " .. get_players());

if (get_players() == 1) then
    session:streamFile("freeship/waitingforplayers.wav");
-- Check if player #2 to start game
elseif (get_players() == 2) then
    -- Get next player id
    next_player = get_next_player(player_id);

    -- Send next turn event
    custom_msg = "player_id\n" .. next_player .. "\n"; 
    local e = freeswitch.Event("custom", "freeship::next_turn");
    e:addHeader("Player-ID", next_player);
    e:fire();
end

function iterToArray(iter)
    local arr = {};
    for v in iter do
        arr[#arr + 1] = v;
    end
    return arr;
end

function takeTurn()
    local callIds = {};
    dbh:query("SELECT call_id FROM players WHERE active = 't'", function(row)
        -- if(row.call_id ~= session_id) then
            callIds[#callIds + 1] = row.call_id
        -- end
    end);

    for i = 1, #callIds do
        if(callIds[i] ~= session_id) then
            local waitMusic = { 'music1', 'music2', 'music3', 'music4', 'music5', 'music6' }
	    api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/waitforturn.wav aleg");
            api:executeString("uuid_broadcast " .. callIds[i] .. " say::en\\sname_spelled\\siterated\\s" .. player_id .. " aleg");
            api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/" .. waitMusic[ math.random( #waitMusic )] ..".wav aleg");
        end
    end

    digits = session:playAndGetDigits(2, 2, 1, 10000, "#", "freeship/yourturn.wav", "freeship/invaliddigits.wav", "^[0-9*][0-9*]$");
    local x = digits:sub(1, 1);
    local y = digits:sub(2, 2);
    freeswitch.consoleLog("debug", "digits " .. digits);

    if(digits:len() == 2) then
        if(x == '*') then x = 10; end
        if(x == '0') then x = 11; end
        if(y == '*') then y = 10; end
        if(y == '0') then y = 11; end

        -- session:streamFile("freeship/missilelaunch.wav");
        for i = 1, #callIds do
            -- freeswitch.consoleLog("debug", "uuid_broadcast " .. callIds[i] .. " freeship/missilelaunch.wav aleg");
            api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/missilelaunch.wav aleg");
        end
        session:sleep(5000);

        local hitPlayerId = 0;
        dbh:query("SELECT player_id FROM map WHERE x = " .. x .. " AND y = " .. y .. " AND tile_state = 1", function(row)
            hitPlayerId = row.player_id
        end);

        if(hitPlayerId == 0) then
            for i = 1, #callIds do
                api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/miss.wav aleg");
            end

            dbh:query("INSERT INTO scoreboard (player_id, x, y, hit) VALUES (" .. player_id .. ", " .. x .. ", " .. y .. ", false)");
        else
            for i = 1, #callIds do
                api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/hit.wav aleg");
                api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/youwerehit-1.wav aleg");
                api:executeString("uuid_broadcast " .. callIds[i] .. " say::en\\sname_spelled\\siterated\\s" .. hitPlayerId .. " aleg");
                api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/youwerehit-2.wav aleg");
                api:executeString("uuid_broadcast " .. callIds[i] .. " say::en\\sname_spelled\\siterated\\s" .. player_id .. " aleg");
            end

            dbh:query("INSERT INTO scoreboard (player_id, x, y, hit) VALUES (" .. player_id .. ", " .. x .. ", " .. y .. ", true)");
            dbh:query("UPDATE map SET tile_state = 2 WHERE x = " .. x .. " AND y = " .. y);

            -- Hang up the big fat loser
            local alive = false;
            dbh:query("SELECT SUM(CASE WHEN tile_state = 1 THEN 1 ELSE 0 END) AS lives FROM map WHERE player_id = " .. hitPlayerId, function(row)
                if (tonumber(row.lives) > 0) then
                    alive = true;
                else
                    alive = false;
                end
            end);

            if (alive == false) then
                for i = 1, #callIds do
                    api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/sunk-1.wav aleg");
                    api:executeString("uuid_broadcast " .. callIds[i] .. " say::en\\sname_spelled\\siterated\\s" .. hitPlayerId .. " aleg");
                    api:executeString("uuid_broadcast " .. callIds[i] .. " freeship/sunk-2.wav aleg");
                end
                dbh.query("SELECT call_id FROM players WHERE player_id = " .. hitPlayerId, function(row)
                    api:executeString("uuid_kill " .. row.call_id);
                end);
            end
        end
    end

    -- Get next player id
    next_player = get_next_player(player_id);
    freeswitch.consoleLog("debug", "[FREESHIP] sending next_turn to " .. next_player);
    
    -- Send next turn event
    custom_msg = "player_id\n" .. next_player .. "\n";
    local e = freeswitch.Event("custom", "freeship::next_turn");
    e:addHeader("Player-ID", next_player);
    e:fire();
end

-- Listen to events
con = freeswitch.EventConsumer("CUSTOM");

--api:executeString("luarun medialoop.lua " .. session_id);

-- Loop waiting for turn
while (session:ready() == true) do
    freeswitch.consoleLog("debug", "[FREESHIP] Waiting for turn... Player " .. player_id);
    session:sleep(1000);
    --freeswitch.consoleLog("debug", "[FREESHIP] " .. session_id .. " freeship/" .. waitMusic[ math.random( #waitMusic )] ..".wav aleg");


    -- Check for min number of players
    dbh:query("SELECT COUNT(*) AS num FROM players WHERE active = 't'", function(row) 
        players = tonumber(row.num);
    end);
    if (players < 2) then
        for e in (function() return con:pop() end) do
        end
    end

    -- Handle events
    for e in (function() return con:pop() end) do
        local plid = e:getHeader("Player-ID");
        if (plid ~= nil) then
            if(plid == player_id) then
                freeswitch.consoleLog("debug", "[FREESHIP] my turn");
                takeTurn();
            end
        end

        --freeswitch.consoleLog("debug", "[FREESHIP] Event:\n" .. e:serialize("xml"));

    end
end

-- Return/hangup
session:hangup();
