# CoWorkSpaces response interpreter (RouterOS 7.12+), script version {{SCRIPT_VERSION}}.
# Stored as the system script "cws-interpreter". The server answers with plain text:
#   CWS-SYNC <nonce>            (the nonce must echo the one we sent, or the whole response is dropped)
#   C|command_id|verb|arg1|arg2|arg3
# The response text is NEVER executed. Each line is split into fields, the verb is matched against a
# fixed allowlist and every argument is validated before one hard-coded command runs.

:global cwsAcks
:global cwsAllowedVerbs {"upsert-user"; "disable-user"; "remove-active"; "block-mac"; "set-key"; "update-script"; "upsert-profile"}
# Verbs that still work in poll-only mode: they never touch hotspot users, profiles or bindings.
:global cwsPollOnlyVerbs {"set-key"; "update-script"}
:global cwsScriptNames {"cws-hooks"; "cws-sync"; "cws-snapshot"; "cws-interpreter"}

:global cwsSplit do={
  :local parts [:toarray ""]
  :local start 0
  :local pos [:find $1 $2 $start]
  :while ([:typeof $pos] = "num") do={
    :set parts ($parts, [:pick $1 $start $pos])
    :set start ($pos + 1)
    :set pos [:find $1 $2 $start]
  }
  :set parts ($parts, [:pick $1 $start [:len $1]])
  :return $parts
}

:global cwsInList do={
  :foreach item in=$2 do={ :if ($item = $1) do={ :return true } }
  :return false
}

# Returns "ok" or a short rejection reason; every branch runs one fixed command with validated arguments.
:global cwsRunCommand do={
  :global cwsMode; :global cwsInList; :global cwsAllowedVerbs; :global cwsPollOnlyVerbs; :global cwsScriptNames
  :local verb ($1->2)
  :local a1 ($1->3)
  :local a2 ($1->4)
  :local a3 ($1->5)
  :local namePattern "^[A-Za-z0-9._@-]{1,64}\$"
  :local macPattern "^([0-9A-F]{2}:){5}[0-9A-F]{2}\$"

  :if (![$cwsInList $verb $cwsAllowedVerbs]) do={ :return "not-allowed" }
  :if ($cwsMode = "poll-only" && ![$cwsInList $verb $cwsPollOnlyVerbs]) do={ :return "poll-only" }

  :if ($verb = "upsert-user") do={
    :if (!($a1 ~ $namePattern) || [:len $a2] < 1 || [:len $a2] > 64) do={ :return "bad-args" }
    :local profile $a3
    :if ([:len $profile] = 0) do={ :set profile "visitor" }
    :if (!($profile ~ $namePattern)) do={ :return "bad-args" }
    :local id [/ip hotspot user find where name=$a1]
    :if ([:len $id] = 0) do={
      /ip hotspot user add name=$a1 password=$a2 profile=$profile comment="cws"
    } else={
      /ip hotspot user set $id password=$a2 profile=$profile disabled=no
    }
    :return "ok"
  }
  :if ($verb = "disable-user") do={
    :if (!($a1 ~ $namePattern)) do={ :return "bad-args" }
    /ip hotspot user set [find where name=$a1] disabled=yes
    /ip hotspot active remove [find where user=$a1]
    :return "ok"
  }
  :if ($verb = "remove-active") do={
    :if (!($a1 ~ $namePattern)) do={ :return "bad-args" }
    /ip hotspot active remove [find where user=$a1]
    :return "ok"
  }
  :if ($verb = "block-mac") do={
    :if (!($a1 ~ $macPattern)) do={ :return "bad-args" }
    :if ([:len [/ip hotspot ip-binding find where mac-address=$a1 and type=blocked]] = 0) do={
      /ip hotspot ip-binding add mac-address=$a1 type=blocked comment="cws"
    }
    :return "ok"
  }
  :if ($verb = "upsert-profile") do={
    # shared-users is forced to 1 whatever the server sends: one account, one device.
    :if (!($a1 ~ $namePattern) || !($a2 ~ "^[0-9A-Za-z/]{0,40}\$")) do={ :return "bad-args" }
    :local id [/ip hotspot user profile find where name=$a1]
    :if ([:len $id] = 0) do={
      /ip hotspot user profile add name=$a1 shared-users=1 rate-limit=$a2 comment="cws"
    } else={
      /ip hotspot user profile set $id shared-users=1 rate-limit=$a2
    }
    :return "ok"
  }
  :if ($verb = "set-key") do={
    :if (!($a1 ~ "^[0-9a-f]{32,96}\$")) do={ :return "bad-args" }
    :global cwsKey $a1
    :global cwsSaveState
    $cwsSaveState
    :return "ok"
  }
  :if ($verb = "update-script") do={
    # Only our own script names, fetched from the configured sync host, and only if the sha256 matches.
    :if (![$cwsInList $a1 $cwsScriptNames] || !($a2 ~ "^[0-9a-f]{64}\$")) do={ :return "bad-args" }
    :global cwsUpdateScript
    :return [$cwsUpdateScript $a1 $a2]
  }
  :return "not-allowed"
}

# Verifies the nonce echo, then runs every "C|" line and queues "A|id|status" acknowledgments.
:global cwsInterpret do={
  :global cwsAcks; :global cwsSplit; :global cwsRunCommand
  :local lines [$cwsSplit $1 "\n"]
  :local header [$cwsSplit ($lines->0) " "]
  :if (($header->0) != "CWS-SYNC" || ($header->1) != $2) do={
    :log warning "cws: response rejected (missing CWS-SYNC header or nonce mismatch)"
    :return false
  }
  :foreach line in=$lines do={
    :if ([:pick $line 0 2] = "C|") do={
      :local fields [$cwsSplit $line "|"]
      :local result "not-allowed"
      :do { :set result [$cwsRunCommand $fields] } on-error={ :set result "failed" }
      :if ($result != "ok") do={ :log warning "cws: command $($fields->1) ($($fields->2)) ignored: $result" }
      :set cwsAcks ($cwsAcks . "A|$($fields->1)|$result\n")
    }
  }
  :return true
}
