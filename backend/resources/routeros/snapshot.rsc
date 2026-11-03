# CoWorkSpaces snapshot builder (RouterOS 7.12+), script version {{SCRIPT_VERSION}}.
# Stored as the system script "cws-snapshot". Reads /ip hotspot active (read-only, safe in every
# mode) and splits it into chunks that are sent one per sync cycle. Chunks are capped well below
# the 4KB budget so every request/response pair stays small.

:global cwsSnapChunks
:global cwsSnapId
:global cwsSnapTotal 0
:global cwsSnapSize 3000

# Builds the chunk list from the active sessions; each row is "user|mac|ip|uptime|bytes-in|bytes-out".
:global cwsBuildSnapshot do={
  :global cwsSnapChunks; :global cwsSnapId; :global cwsSnapTotal; :global cwsSnapSize; :global cwsClean
  :set cwsSnapId [:rndstr length=10 from="abcdef0123456789"]
  :local chunks [:toarray ""]
  :local current ""
  :foreach a in=[/ip hotspot active find] do={
    :local row [/ip hotspot active get $a]
    :local line "$[$cwsClean ($row->"user")]|$($row->"mac-address")|$($row->"address")|$($row->"uptime")|$($row->"bytes-in")|$($row->"bytes-out")\n"
    :if ([:len $current] + [:len $line] > $cwsSnapSize) do={
      :set chunks ($chunks, $current)
      :set current ""
    }
    :set current ($current . $line)
  }
  # The last chunk is always sent, even when empty: an empty snapshot tells the server nobody is online.
  :set chunks ($chunks, $current)
  :set cwsSnapChunks $chunks
  :set cwsSnapTotal [:len $chunks]
  :return $cwsSnapTotal
}

# Pops the next chunk as "S|snapshot_id|index|total" followed by its rows, or "" when none is pending.
:global cwsNextSnapshotChunk do={
  :global cwsSnapChunks; :global cwsSnapId; :global cwsSnapTotal
  :if ([:typeof $cwsSnapChunks] != "array" || [:len $cwsSnapChunks] = 0) do={ :return "" }
  :local remaining [:len $cwsSnapChunks]
  :local index ($cwsSnapTotal - $remaining + 1)
  :local chunk ($cwsSnapChunks->0)
  :local rest [:toarray ""]
  :for i from=1 to=($remaining - 1) do={ :set rest ($rest, ($cwsSnapChunks->$i)) }
  :set cwsSnapChunks $rest
  :return "S|$cwsSnapId|$index|$cwsSnapTotal\n$chunk"
}
