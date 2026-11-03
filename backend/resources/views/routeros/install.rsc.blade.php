# CoWorkSpaces router installer for "{!! $routerName !!}"
# One-time script: the key below is not shown again. Do not share this file.
:global cwsSyncUrl "{!! $syncUrl !!}"
:global cwsWorkspace "{!! $workspaceCode !!}"
:global cwsKey "{!! $key !!}"

:foreach script in={!! $scriptList !!} do={
    /tool fetch url=($cwsSyncUrl . "/api/v1/hotspot/scripts/" . $script . ".rsc") http-header-field=("X-Router-Key: " . $cwsKey) check-certificate=yes dst-path=("cws-" . $script . ".rsc")
    /import file-name=("cws-" . $script . ".rsc")
}
