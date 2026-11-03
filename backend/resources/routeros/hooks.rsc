# CoWorkSpaces hotspot hooks (RouterOS 7.12+), script version {{SCRIPT_VERSION}}.
# Stored as the system script "cws-hooks". Defines the event buffer functions and, in "full" mode,
# attaches on-login / on-logout hooks to the hotspot user profiles.
# In "poll-only" mode (parallel run) NOTHING on the router is modified: no hooks, no profiles.

:global cwsMode "{{MODE}}"
:global cwsMaxEvents 200
:global cwsEvents
:global cwsEventCount
:global cwsSeq
:global cwsBootId
:if ([:typeof $cwsEvents] = "nothing") do={ :set cwsEvents ""; :set cwsEventCount 0 }
:if ([:typeof $cwsSeq] = "nothing") do={ :set cwsSeq 0 }
:if ([:typeof $cwsBootId] = "nothing") do={ :set cwsBootId [:rndstr length=8 from="abcdef0123456789"] }

# Strips the field and line separators so a user name can never break the batch format.
:global cwsClean do={
  :local out ""
  :for i from=0 to=([:len $1] - 1) do={
    :local c [:pick $1 $i ($i + 1)]
    :if ($c = "|" || $c = "\n" || $c = "\r") do={ :set c "_" }
    :set out ($out . $c)
  }
  :return $out
}

# Appends one event line "E|seq|type|date time|user|mac|ip|uptime|bytes-in|bytes-out" to the RAM buffer.
# The seq is reserved here; it is persisted to flash by the sync cycle.
:global cwsRecord do={
  :global cwsEvents; :global cwsEventCount; :global cwsMaxEvents; :global cwsSeq; :global cwsClean
  :if ($cwsEventCount >= $cwsMaxEvents) do={
    :log warning "cws: event buffer full, event dropped (the next snapshot heals the gap)"
    :return false
  }
  :set cwsSeq ($cwsSeq + 1)
  :local ts "$[/system clock get date] $[/system clock get time]"
  :local line "E|$cwsSeq|$type|$ts|$[$cwsClean $user]|$mac|$ip|$uptime|$bytesIn|$bytesOut\n"
  :set cwsEvents ($cwsEvents . $line)
  :set cwsEventCount ($cwsEventCount + 1)
  :return true
}

:local loginHook ":global cwsRecord; \$cwsRecord type=login user=\$user mac=\$\"mac-address\" ip=\$address"
:local logoutHook ":global cwsRecord; \$cwsRecord type=logout user=\$user mac=\$\"mac-address\" ip=\$address uptime=\$\"uptime\" bytesIn=\$\"bytes-in\" bytesOut=\$\"bytes-out\""

:if ($cwsMode = "poll-only") do={
  :log info "cws-hooks: poll-only mode, existing hotspot hooks and profiles are left untouched"
} else={
  # Every hotspot user profile gets shared-users=1 (one device per account) and our hooks, appended
  # to whatever hook the owner already had so nothing of theirs is lost.
  :if ([:len [/ip hotspot user profile find where name="visitor"]] = 0) do={
    /ip hotspot user profile add name=visitor shared-users=1 comment="cws"
  }
  :foreach p in=[/ip hotspot user profile find] do={
    :local onLogin [/ip hotspot user profile get $p on-login]
    :local onLogout [/ip hotspot user profile get $p on-logout]
    :if ([:len [:find $onLogin "cwsRecord"]] = 0) do={
      /ip hotspot user profile set $p shared-users=1 on-login=($onLogin . "\n" . $loginHook)
    } else={
      /ip hotspot user profile set $p shared-users=1
    }
    :if ([:len [:find $onLogout "cwsRecord"]] = 0) do={
      /ip hotspot user profile set $p on-logout=($onLogout . "\n" . $logoutHook)
    }
  }
  :log info "cws-hooks: installed on-login/on-logout hooks, shared-users=1 enforced"
}
