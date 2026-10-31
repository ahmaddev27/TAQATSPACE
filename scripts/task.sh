#!/usr/bin/env bash
#
# Roadmap task workflow: links a task issue to its branch, PR and project-board status.
# Prereqs: `gh` logged in with the `project` scope (`gh auth refresh -s project`). Run from the repo root.
#
#   scripts/task.sh start  <task|issue>          # e.g. 2.4 or 312 — linked branch + "In Progress"
#   scripts/task.sh pr     <task|issue>          # push branch, open PR to dev with "Closes #N" + "In Review"
#   scripts/task.sh status <task|issue> <status> # Todo | "In Progress" | "In Review" | Blocked | Done
#   scripts/task.sh show   <task|issue>          # print the issue spec
#
set -euo pipefail

REPO="ahmaddev27/TAQATSPACE"
BASE_BRANCH="dev"
# "TaqatSpace Roadmap" board (user project #1) and its Status field — stable ids, so a status
# change needs no lookups (the gh project subcommands fail with "unknown owner type" on a slow link).
PROJECT_ID="PVT_kwHOA0rXy84BpS36"
STATUS_FIELD_ID="PVTSSF_lAHOA0rXy84BpS36zhnvJHY"

die() { echo "error: $*" >&2; exit 1; }

# The GitHub API is flaky from here: retry a command a few times before giving up.
retry() {
  local attempt
  for attempt in 1 2 3 4 5; do
    "$@" && return 0
    sleep 5
  done
  return 1
}

status_option_id() {
  case "$1" in
    Todo) echo "5aa59488" ;;
    "In Progress") echo "1c226f81" ;;
    "In Review") echo "1c92803e" ;;
    Blocked) echo "93a8a26d" ;;
    Done) echo "aa3e5201" ;;
    *) return 1 ;;
  esac
}

# Accepts a task id (2.4) or an issue number (312) and prints the issue number.
resolve_issue() {
  local ref="$1"
  if [[ "$ref" =~ ^[0-9]+$ ]]; then echo "$ref"; return; fi
  [[ "$ref" =~ ^[0-9]{1,2}\.[0-9]{1,2}$ ]] || die "expected a task id like 2.4 or an issue number"
  local number
  number=$(gh issue list -R "$REPO" --state all --limit 300 --json number,title \
    --jq ".[] | select(.title | startswith(\"[M$ref]\")) | .number" | head -1)
  [ -n "$number" ] || die "no issue titled [M$ref]"
  echo "$number"
}

issue_title() { gh issue view "$1" -R "$REPO" --json title --jq .title; }

# Branch name is recorded in the issue body table: | **Branch** | `feat/m2-4` |
issue_branch() {
  gh issue view "$1" -R "$REPO" --json body --jq .body | grep -o '`[a-z]*/m[0-9-]*`' | head -1 | tr -d '`'
}

# Prints "<issue node id> <board item id or empty>" for an issue number.
issue_board_ids() {
  gh api graphql -f query="query{ repository(owner:\"${REPO%/*}\",name:\"${REPO#*/}\"){ issue(number:$1){ id
    projectItems(first:10){ nodes{ id project{ id } } } } } }" \
    --jq ".data.repository.issue | \"\(.id) \([.projectItems.nodes[] | select(.project.id == \"$PROJECT_ID\") | .id][0] // \"\")\""
}

set_status() {
  local issue="$1" status="$2" option_id ids node_id item_id
  option_id=$(status_option_id "$status") || die "status \"$status\" is not an option on the board"
  ids=$(retry issue_board_ids "$issue") || die "could not read issue #$issue"
  node_id=${ids%% *}
  item_id=${ids#* }
  if [ -z "$item_id" ]; then
    item_id=$(retry gh api graphql -f query="mutation{ addProjectV2ItemById(input:{projectId:\"$PROJECT_ID\",contentId:\"$node_id\"}){ item{ id } } }" \
      --jq .data.addProjectV2ItemById.item.id) || die "could not add issue #$issue to the board"
  fi
  retry gh api graphql -f query="mutation{ updateProjectV2ItemFieldValue(input:{projectId:\"$PROJECT_ID\",itemId:\"$item_id\",fieldId:\"$STATUS_FIELD_ID\",value:{singleSelectOptionId:\"$option_id\"}}){ projectV2Item{ id } } }" \
    >/dev/null || die "could not set #$issue to $status"
  echo "#$issue → $status"
}

cmd_start() {
  local issue branch
  issue=$(resolve_issue "$1")
  branch=$(issue_branch "$issue")
  [ -n "$branch" ] || die "no branch recorded in issue #$issue"
  git fetch origin "$BASE_BRANCH" --quiet
  gh issue develop "$issue" -R "$REPO" --base "$BASE_BRANCH" --name "$branch" --checkout
  gh issue edit "$issue" -R "$REPO" --add-assignee @me >/dev/null
  set_status "$issue" "In Progress"
}

cmd_pr() {
  local issue title branch
  issue=$(resolve_issue "$1")
  title=$(issue_title "$issue")
  branch=$(git rev-parse --abbrev-ref HEAD)
  git push -u origin "$branch"
  gh pr create -R "$REPO" --base "$BASE_BRANCH" --head "$branch" --title "$title" \
    --body "$(printf 'Closes #%s\n\n## What changed\n- \n\n## How it was verified\n- \n' "$issue")"
  set_status "$issue" "In Review"
}

main() {
  local command="${1:-}"
  case "$command" in
    start)  [ $# -eq 2 ] || die "usage: task.sh start <task|issue>"; cmd_start "$2" ;;
    pr)     [ $# -eq 2 ] || die "usage: task.sh pr <task|issue>"; cmd_pr "$2" ;;
    status) [ $# -eq 3 ] || die "usage: task.sh status <task|issue> <status>"; set_status "$(resolve_issue "$2")" "$3" ;;
    show)   [ $# -eq 2 ] || die "usage: task.sh show <task|issue>"; gh issue view "$(resolve_issue "$2")" -R "$REPO" ;;
    *)      sed -n '3,10p' "$0"; exit 1 ;;
  esac
}

main "$@"
