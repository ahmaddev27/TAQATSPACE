# CoWorkSpaces router installer for "Lobby"
# One-time script: the key below is not shown again. Do not share this file.
:global cwsSyncUrl "https://sync.taqat.space"
:global cwsWorkspace "WS-ABC123"
:global cwsKey "KEY"

:foreach script in={"install-precheck";"hooks";"sync";"snapshot";"interpreter"} do={
    /tool fetch url=($cwsSyncUrl . "/api/v1/hotspot/scripts/" . $script . ".rsc") http-header-field=("X-Router-Key: " . $cwsKey) check-certificate=yes dst-path=("cws-" . $script . ".rsc")
    /import file-name=("cws-" . $script . ".rsc")
}
