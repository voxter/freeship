local dbh = freeswitch.Dbh("odbc://freeship:postgres:postgres");

local seed = session:getVariable("created_time");
math.randomseed(seed);

function table_print (tt, indent, done)
  done = done or {}
  indent = indent or 0
  if type(tt) == "table" then
    local sb = {}
    for key, value in pairs (tt) do
      table.insert(sb, string.rep (" ", indent)) -- indent it
      if type (value) == "table" and not done [value] then
        done [value] = true
        table.insert(sb, "{\n");
        table.insert(sb, table_print (value, indent + 2, done))
        table.insert(sb, string.rep (" ", indent)) -- indent it
        table.insert(sb, "}\n");
      elseif "number" == type(key) then
        table.insert(sb, string.format("\"%s\"\n", tostring(value)))
      else
        table.insert(sb, string.format(
            "%s = \"%s\"\n", tostring (key), tostring(value)))
       end
    end
    return table.concat(sb)
  else
    return tt .. "\n"
  end
end

function to_string( tbl )
    if  "nil"       == type( tbl ) then
        return tostring(nil)
    elseif  "table" == type( tbl ) then
        return table_print(tbl)
    elseif  "string" == type( tbl ) then
        return tbl
    else
        return tostring(tbl)
    end
end

-- ship_type: 0 - 2 square (destroyer), 1 - 3 square (battleship)
function build_ship(ship_type)
	-- orientation: 0 - horizontal, 1 - vertical
	local orientation = math.random(0,1);
	local ship_coords = {["x1"]=0,["y1"]=0,["x2"]=0,["y2"]=0,["x3"]=0,["y3"]=0};
	local ship_size = 0;

	if (ship_type == 0) then
		ship_size = 2;
	else
		ship_size = 3;
	end

	if (orientation == 0) then -- horizontal
		ship_coords["x1"] = math.random(1,(11-tonumber(ship_size)));
		ship_coords["y1"] = math.random(1,11);
		ship_coords["x2"] = ship_coords["x1"]+1;
		ship_coords["y2"] = ship_coords.y1;
		
		-- Add another square if battleship
		if(ship_type == 1) then 
			ship_coords["x3"] = ship_coords["x2"]+1;
			ship_coords["y3"] = ship_coords["y2"];
		end
	else -- vertical
		ship_coords["x1"] = math.random(1,11);
		ship_coords["y1"] = math.random(1,(11-tonumber(ship_size)));
		ship_coords["x2"] = ship_coords["x1"];
		ship_coords["y2"] = ship_coords["y1"]+1;
		
		-- Add another square if battleship
		if (ship_type == 1) then 
			ship_coords["x3"] = ship_coords["x2"];
			ship_coords["y3"] = ship_coords["y2"]+1;
		end
	end

	return {
		["orientation"] = orientation,
		["coordinates"] = ship_coords,
		["size"] = ship_size
	};
end

function test_spaces(ship)	
	local available = false; -- Assume spaces are not available
	local sql1 = "SELECT COUNT(*) AS used FROM map WHERE (x = " .. ship["coordinates"]["x1"] .. " AND y = " .. ship["coordinates"]["y1"] .. ")";
	local sql2 = "SELECT COUNT(*) AS used FROM map WHERE (x = " .. ship["coordinates"]["x2"] .. " AND y = " .. ship["coordinates"]["y2"] .. ")"; 
	local sql3 = "SELECT COUNT(*) AS used FROM map WHERE (x = " .. ship["coordinates"]["x3"] .. " AND y = " .. ship["coordinates"]["y3"] .. ")";

	dbh:query(sql1, function(row)
		available = (tonumber(row.used) == 0);
	end);
	if (not available) then return false end;

	dbh:query(sql2, function(row)
		available = (tonumber(row.used) == 0);
	end);
	if (not available) then return false end;

	if (ship["size"] == 3) then
		dbh:query(sql3, function(row)
			available = (tonumber(row.used) == 0);
		end);
		if (not available) then return false end;
	end

	return available;
end

-- ship_type: 0 - 2 square (destroyer), 1 - 3 square (battleship)
function place_ship(player_id, ship_type)
	local max_tries = 10;
	local available = false;
	local ship = {};
	local ship_name = "destroyer";
	if (ship_type == 1) then ship_name = "battleship" end;

	local tries = 0;
	while (not available and tries < max_tries) do
		-- Build a ship and test to see if it can be placed
		ship = build_ship(ship_type);
		freeswitch.consoleLog("info", "[FREESHIP] Building " .. ship_name .. ": " .. to_string(ship));
		
		available = test_spaces(ship);
		freeswitch.consoleLog("info", "[FREESHIP] Space available to place " .. ship_name .. ": " .. tostring(available));

		tries = tries + 1;
	end

	if (available) then
		dbh:query("INSERT INTO map VALUES (" .. ship["coordinates"]["x1"] .. ", " .. ship["coordinates"]["y1"] .. ", 1, " .. player_id .. ", " .. ship_type .. ")");
		dbh:query("INSERT INTO map VALUES (" .. ship["coordinates"]["x2"] .. ", " .. ship["coordinates"]["y2"] .. ", 1, " .. player_id .. ", " .. ship_type .. ")");

		if (ship_type == 1) then
		    dbh:query("INSERT INTO map VALUES (" .. ship["coordinates"]["x3"] .. ", " .. ship["coordinates"]["y3"] .. ", 1, " .. player_id .. ", " .. ship_type .. ")");			   return "{\"event\":\"setup\",\"grid\":{\"x1\":"..ship["coordinates"]["x1"]..",\"y1\":"..ship["coordinates"]["y1"]..",\"x2\":"..ship["coordinates"]["x2"]..",\"y2\":"..ship["coordinates"]["y2"]..",\"x3\":"..ship["coordinates"]["x3"]..",\"y3\":"..ship["coordinates"]["y3"].."}}";
		else
                    return "{\"event\":\"setup\",\"grid\":{\"x1\":"..ship["coordinates"]["x1"]..",\"y1\":"..ship["coordinates"]["y1"]..",\"x2\":"..ship["coordinates"]["x2"]..",\"y2\":"..ship["coordinates"]["y2"].."}}";
                end
	end
end

function remove_ships(player_id)
	freeswitch.consoleLog("info", "[FREESHIP] Removing ships for player ID: " .. tostring(player_id));
	dbh:query("DELETE FROM map WHERE player_id = " .. tostring(player_id));
end
