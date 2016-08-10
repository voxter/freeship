
local session_id = argv[1];

-- Create API Object
api = freeswitch.API();

while(true) do
    local waitMusic = { 'music1', 'music2', 'music3', 'music4', 'music5', 'music6' }
    freeswitch.consoleLog("debug", "[FREESHIP] playing media for " .. session_id);
    api:executeString("uuid_broadcast " .. session_id .. " freeship/" .. waitMusic[ math.random( #waitMusic )] ..".wav aleg");
    os.execute("sleep 20000");
end