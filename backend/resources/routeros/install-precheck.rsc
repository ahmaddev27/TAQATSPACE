# CoWorkSpaces install pre-checks (RouterOS 7.12+), script version {{SCRIPT_VERSION}}.
# Aborts the installation (before anything is changed) when the router cannot run the sync scripts.
# Placeholders in double braces are injected by the server when the install script is generated.

:local minMajor 7
:local minMinor 12
:local failures 0

# RouterOS version, e.g. "7.14.3 (stable)" -> 7 / 14
:local ver [/system resource get version]
:local dot1 [:find $ver "."]
:local major [:tonum [:pick $ver 0 $dot1]]
:local rest [:pick $ver ($dot1 + 1) [:len $ver]]
:local minor [:tonum [:pick $rest 0 [:find $rest "."]]]
:if ($major < $minMajor || ($major = $minMajor && $minor < $minMinor)) do={
  :log error "cws-precheck: RouterOS $minMajor.$minMinor+ is required, found $ver"
  :set failures ($failures + 1)
}

# Clock: routers without a battery boot in 1970 until NTP answers; events would be timestamped wrongly.
:local clockDate [/system clock get date]
:if ([:len [:find $clockDate "1970"]] > 0) do={
  :log error "cws-precheck: system clock is not set ($clockDate); enable the NTP client first"
  :set failures ($failures + 1)
}

# Required packages: the hotspot feature must be present and enabled.
:if ([:len [/system package find where name="hotspot" and disabled=yes]] > 0) do={
  :log error "cws-precheck: the hotspot package is disabled"
  :set failures ($failures + 1)
}
:if ([:len [/ip hotspot find]] = 0) do={
  :log error "cws-precheck: no hotspot server is configured"
  :set failures ($failures + 1)
}

# /tool fetch must reach the sync host over HTTPS with certificate checking.
:do {
  /tool fetch url="{{SYNC_URL}}/api/v1/hotspot/ping" check-certificate=yes output=none
} on-error={
  :log error "cws-precheck: cannot reach {{SYNC_URL}} with a valid certificate"
  :set failures ($failures + 1)
}

:if ($failures > 0) do={
  :error "cws-precheck failed ($failures problem(s)); see /log print where message~\"cws-precheck\""
}
:log info "cws-precheck: all checks passed"
