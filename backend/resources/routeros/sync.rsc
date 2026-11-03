# CoWorkSpaces sync cycle (RouterOS 7.12+), script version {{SCRIPT_VERSION}}.
# Stored as the system script "cws-sync"; the scheduler "cws-sync" runs it every 50 seconds.
#
# Wire format reference: DOCS/integration/router-protocol.md (keep both in sync; a contract test reads this file).
# Request (plain text, POST {{SYNC_URL}}/api/v1/hotspot/{{ROUTER_CODE}}/sync, header X-API-Key):
#   H|batch_id|seq|boot_id|now|mode|nonce|script_version|serial|routeros_version
#   E|seq|type|date time|user|mac|ip|uptime|bytes-in|bytes-out      (zero or more events)
#   S|snapshot_id|index|total  + rows "user|mac|ip|uptime|bytes-in|bytes-out"   (at most one chunk)
#   A|command_id|status                                           (acknowledgments, status ok|<reason>)
# Response: see interpreter.rsc. Event identity on the server is boot_id-seq, so a resend is harmless.
# The key and the last seq live in the flash file cws-state.txt, so they survive reboots.

:global cwsKey
:global cwsAcks
:global cwsLastBatch
:global cwsLastBody
:global cwsCycles
:global cwsReady
:global cwsStateFile "flash/cws-state.txt"
:if ([:len [/file find where name="flash"]] = 0) do={ :set cwsStateFile "cws-state.txt" }
:if ([:typeof $cwsAcks] = "nothing") do={ :set cwsAcks "" }
:if ([:typeof $cwsCycles] = "nothing") do={ :set cwsCycles 0 }

# State file format: "key=<hex>" and "seq=<n>" on separate lines.
:global cwsSaveState do={
  :global cwsKey; :global cwsSeq; :global cwsStateFile
  :if ([:len [/file find where name=$cwsStateFile]] = 0) do={ /file print file=($cwsStateFile) where false }
  /file set [find where name=$cwsStateFile] contents="key=$cwsKey\nseq=$cwsSeq"
}

:global cwsLoadState do={
  :global cwsKey; :global cwsSeq; :global cwsStateFile; :global cwsSplit
  :if ([:len [/file find where name=$cwsStateFile]] = 0) do={ :return false }
  :foreach line in=[$cwsSplit [/file get [find where name=$cwsStateFile] contents] "\n"] do={
    :if ([:pick $line 0 4] = "key=") do={ :set cwsKey [:pick $line 4 [:len $line]] }
    :if ([:pick $line 0 4] = "seq=") do={ :set cwsSeq [:tonum [:pick $line 4 [:len $line]]] }
  }
  :return true
}

# Replaces one of our own system scripts after verifying the sha256 of the downloaded source.
:global cwsUpdateScript do={
  :global cwsKey
  :local url "{{SYNC_URL}}/api/v1/hotspot/{{ROUTER_CODE}}/scripts/$1"
  :local res ""
  :do {
    :set res ([/tool fetch url=$url http-header-field="X-API-Key: $cwsKey" check-certificate=yes output=user as-value]->"data")
  } on-error={ :return "failed" }
  :if ([:convert $res transform=sha256 to=hex] != $2) do={ :return "sha-mismatch" }
  /system script set [find where name=$1] source=$res
  :return "ok"
}

# One-time setup after a reboot (or the first run): functions, flash state, boot id.
:if ([:typeof $cwsReady] = "nothing") do={
  /system script run cws-hooks
  /system script run cws-snapshot
  /system script run cws-interpreter
  :global cwsSeq; :global cwsLoadState
  # The served script carries no key, so an update keeps the key already in memory or in flash.
  :if ([:typeof $cwsKey] = "nothing" || $cwsKey = "") do={ :set cwsKey "{{API_KEY}}" }
  $cwsLoadState
  :set cwsReady true
}

:global cwsMode; :global cwsEvents; :global cwsEventCount; :global cwsSeq; :global cwsBootId
:global cwsBuildSnapshot; :global cwsNextSnapshotChunk; :global cwsInterpret; :global cwsSaveState

# A failed send keeps the exact same batch (same batch_id, same events) so the server can dedupe it.
:if ([:typeof $cwsLastBody] = "nothing" || $cwsLastBody = "") do={
  # Snapshot cadence: every 36 cycles (~30 min) normally, every cycle in poll-only mode where it is
  # the only source of data because no hooks are installed.
  :local snapshotEvery 36
  :if ($cwsMode = "poll-only") do={ :set snapshotEvery 1 }
  :local pending [$cwsNextSnapshotChunk]
  :if ($pending = "" && ($cwsCycles % $snapshotEvery) = 0) do={
    $cwsBuildSnapshot
    :set pending [$cwsNextSnapshotChunk]
  }
  :set cwsCycles ($cwsCycles + 1)
  :local nonce [:rndstr length=12 from="abcdefghijklmnopqrstuvwxyz0123456789"]
  :set cwsLastBatch [:rndstr length=12 from="abcdef0123456789"]
  :local now "$[/system clock get date] $[/system clock get time]"
  :local serial ""
  :do { :set serial [/system routerboard get serial-number] } on-error={ :set serial "" }
  :local rosVersion [/system resource get version]
  :local head "H|$cwsLastBatch|$cwsSeq|$cwsBootId|$now|$cwsMode|$nonce|{{SCRIPT_VERSION}}|$serial|$rosVersion\n"
  :set cwsLastBody ($head . $cwsEvents . $pending . $cwsAcks)
  :global cwsLastNonce $nonce
  :set cwsEvents ""
  :set cwsEventCount 0
  :set cwsAcks ""
}

:local response ""
:local sent false
:do {
  :set response ([/tool fetch url="{{SYNC_URL}}/api/v1/hotspot/{{ROUTER_CODE}}/sync" http-method=post \
    http-header-field="X-API-Key: $cwsKey,Content-Type: text/plain" http-data=$cwsLastBody \
    check-certificate=yes output=user as-value]->"data")
  :set sent true
} on-error={ :log warning "cws-sync: send failed, the batch is kept for the next cycle" }

:if ($sent) do={
  :global cwsLastNonce
  :set cwsLastBody ""
  $cwsSaveState
  [$cwsInterpret $response $cwsLastNonce]
}
