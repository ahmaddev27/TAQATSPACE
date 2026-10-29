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
OWNER="ahmaddev27"
PROJECT_TITLE="TaqatSpace Roadmap"
BASE_BRANCH="dev"

die() { echo "error: $*" >&2; exit 1; }

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

# Branch name is recorded in the issue body table: | **الفرع** | `feat/m2-4` |
issue_branch() {
  gh issue view "$1" -R "$REPO" --json body --jq .body | grep -o '`[a-z]*/m[0-9-]*`' | head -1 | tr -d '`'
}

project_number() {
  gh project list --owner "$OWNER" --format json --jq ".projects[] | select(.title == \"$PROJECT_TITLE\") | .number"
}

set_status() {
  local issue="$1" status="$2" number project_id field_id option_id item_id
  number=$(project_number)
  [ -n "$number" ] || { echo "warning: project \"$PROJECT_TITLE\" not found — status not updated" >&2; return 0; }
  project_id=$(gh project view "$number" --owner "$OWNER" --format json --jq .id)
  field_id=$(gh project field-list "$number" --owner "$OWNER" --format json --jq '.fields[] | select(.name == "Status") | .id')
  option_id=$(gh project field-list "$number" --owner "$OWNER" --format json \
    --jq ".fields[] | select(.name == \"Status\") | .options[] | select(.name == \"$status\") | .id")
  [ -n "$option_id" ] || die "status \"$status\" is not an option on the board"
  item_id=$(gh project item-list "$number" --owner "$OWNER" --limit 500 --format json \
    --jq ".items[] | select(.content.number == $issue) | .id")
  if [ -z "$item_id" ]; then
    item_id=$(gh project item-add "$number" --owner "$OWNER" --url "https://github.com/$REPO/issues/$issue" --format json --jq .id)
  fi
  gh project item-edit --id "$item_id" --project-id "$project_id" --field-id "$field_id" --single-select-option-id "$option_id" >/dev/null
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
    --body "$(printf 'Closes #%s\n\n## ما تم\n- \n\n## كيف تم التحقق\n- \n' "$issue")"
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
