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
#
# Retry rules (details in the protocol doc):
# - A pending batch keeps its batch_id, seq, nonce, events, snapshot chunk and acks across failed sends.
#   The H line is NOT part of the kept batch: it is rebuilt at every attempt, so "now" is always the
#   current clock and a long outage cannot shift the event times on the server.
# - A batch holds at most cwsBatchEvents events (the server limit) and cwsBatchBytes bytes of events
#   (http-data is limited to 64 KB on RouterOS); the rest stays in the buffer, in order.
# - A batch that fails cwsQuarantineAfter times in a row is dropped, but only when a header-only probe
#   shows the server is reachable (so it is the content being refused, not an outage).

:global cwsKey
:global cwsAcks
:global cwsLastBatch
:global cwsLastNonce
:global cwsLastSeq
:global cwsLastEvents
:global cwsLastSnap
:global cwsLastAcks
:global cwsLastBody
:global cwsFailures
:global cwsCycles
:global cwsReady
:global cwsStateFile "flash/cws-state.txt"
:if ([:len [/file find where name="flash"]] = 0) do={ :set cwsStateFile "cws-state.txt" }
:if ([:typeof $cwsAcks] = "nothing") do={ :set cwsAcks "" }
:if ([:typeof $cwsCycles] = "nothing") do={ :set cwsCycles 0 }
:if ([:typeof $cwsLastBatch] = "nothing") do={ :set cwsLastBatch "" }
:if ([:typeof $cwsLastEvents] = "nothing") do={ :set cwsLastEvents ""; :set cwsLastSnap ""; :set cwsLastAcks "" }
:if ([:typeof $cwsFailures] = "nothing") do={ :set cwsFailures 0 }

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

# Removes and returns the oldest buffered event lines: at most cwsBatchEvents lines and, except for the
# first line, at most cwsBatchBytes bytes, so the request stays below the http-data limit.
:global cwsBatchEvents 500
:global cwsBatchBytes 40000
:global cwsQuarantineAfter 5
:global cwsTakeEvents do={
  :global cwsEvents; :global cwsEventCount; :global cwsBatchEvents; :global cwsBatchBytes
  :local total [:len $cwsEvents]
  :local cut 0
  :local taken 0
  :local scanning true
  :while ($scanning && $taken < $cwsBatchEvents && $cut < $total) do={
    :local nl [:find $cwsEvents "\n" $cut]
    :if ([:typeof $nl] = "nil") do={ :set nl ($total - 1) }
    :if ($taken > 0 && ($nl + 1) > $cwsBatchBytes) do={
      :set scanning false
    } else={
      :set cut ($nl + 1)
      :set taken ($taken + 1)
    }
  }
  :local lines [:pick $cwsEvents 0 $cut]
  :set cwsEvents [:pick $cwsEvents $cut $total]
  :set cwsEventCount ($cwsEventCount - $taken)
  :return $lines
}

# One request: builds the H line from the current clock, posts it with the kept batch body and returns
# the reply text, or false when the send failed. Arguments: batch_id, seq, nonce, body.
:global cwsSend do={
  :global cwsKey; :global cwsBootId; :global cwsMode
  :local batch $1
  :local seq $2
  :local nonce $3
  :local body $4
  :local now "$[/system clock get date] $[/system clock get time]"
  :local serial ""
  :do { :set serial [/system routerboard get serial-number] } on-error={ :set serial "" }
  :local rosVersion [/system resource get version]
  :local head "H|$batch|$seq|$cwsBootId|$now|$cwsMode|$nonce|{{SCRIPT_VERSION}}|$serial|$rosVersion\n"
  :local res ""
  :do {
    :set res ([/tool fetch url="{{SYNC_URL}}/api/v1/hotspot/{{ROUTER_CODE}}/sync" http-method=post \
      http-header-field="X-API-Key: $cwsKey,Content-Type: text/plain" http-data=($head . $body) \
      check-certificate=yes output=user as-value]->"data")
  } on-error={ :return false }
  :return $res
}

:global cwsMode; :global cwsEvents; :global cwsEventCount; :global cwsSeq; :global cwsBootId
:global cwsBuildSnapshot; :global cwsNextSnapshotChunk; :global cwsInterpret; :global cwsSaveState
:global cwsTakeEvents; :global cwsSend; :global cwsQuarantineAfter

# A batch frozen by an older script version carried its own H line; keep its content and rebuild the head.
:if ([:typeof $cwsLastBody] != "nothing" && $cwsLastBody != "") do={
  :set cwsLastEvents [:pick $cwsLastBody ([:find $cwsLastBody "\n"] + 1) [:len $cwsLastBody]]
  :set cwsLastSnap ""
  :set cwsLastAcks ""
  :set cwsLastSeq $cwsSeq
  :set cwsLastBody ""
}

# A new batch is frozen only when none is pending; a failed send keeps the same one.
:if ($cwsLastBatch = "") do={
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
  :set cwsLastNonce [:rndstr length=12 from="abcdefghijklmnopqrstuvwxyz0123456789"]
  :set cwsLastBatch [:rndstr length=12 from="abcdef0123456789"]
  :set cwsLastSeq $cwsSeq
  :set cwsLastEvents [$cwsTakeEvents]
  :set cwsLastSnap $pending
  :set cwsLastAcks $cwsAcks
  :set cwsAcks ""
}

:local response [$cwsSend $cwsLastBatch $cwsLastSeq $cwsLastNonce ($cwsLastEvents . $cwsLastSnap . $cwsLastAcks)]

:if ([:typeof $response] = "str") do={
  :set cwsLastBatch ""
  :set cwsLastEvents ""
  :set cwsLastSnap ""
  :set cwsLastAcks ""
  :set cwsFailures 0
  $cwsSaveState
  [$cwsInterpret $response $cwsLastNonce]
} else={
  :set cwsFailures ($cwsFailures + 1)
  :log warning "cws-sync: send failed ($cwsFailures), the batch is kept for the next cycle"
  :if ($cwsFailures >= $cwsQuarantineAfter) do={
    # A header-only request tells an outage (it fails too) from a batch the server refuses (it succeeds).
    :local probeNonce [:rndstr length=12 from="abcdefghijklmnopqrstuvwxyz0123456789"]
    :local probe [$cwsSend [:rndstr length=12 from="abcdef0123456789"] $cwsSeq $probeNonce ""]
    :if ([:typeof $probe] = "str") do={
      :log error "cws-sync: the server keeps refusing batch $cwsLastBatch while it is reachable; the batch (events, snapshot chunk, acks) is dropped so later data can flow"
      :set cwsLastBatch ""
      :set cwsLastEvents ""
      :set cwsLastSnap ""
      :set cwsLastAcks ""
      :set cwsFailures 0
      $cwsSaveState
      [$cwsInterpret $probe $probeNonce]
    }
  }
}
