#!/usr/bin/env bash
# Stop: require a branch memory update when code was committed after the memory last changed.

root=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --show-toplevel 2>/dev/null) || exit 0
branch=$(git -C "$root" symbolic-ref --short HEAD 2>/dev/null) || exit 0
case "$branch" in main|master|develop|staging) exit 0 ;; esac

rel=".claude/memories/$branch.md"
file="$root/$rel"

# Last commit touching anything except the memories themselves
code_ts=$(git -C "$root" log -1 --format=%ct -- . ':(exclude).claude/memories' 2>/dev/null)
code_ts=${code_ts:-0}

# Memory counts as fresh if edited on disk or committed together with the code
if [ -f "$file" ]; then
  mem_ts=$(stat -c %Y "$file")
  mem_commit_ts=$(git -C "$root" log -1 --format=%ct -- "$rel" 2>/dev/null)
  [ "${mem_commit_ts:-0}" -gt "$mem_ts" ] && mem_ts=$mem_commit_ts
  [ "$mem_ts" -ge "$code_ts" ] && exit 0
fi

jq -n --arg f "$rel" '{systemMessage: ("Branch memory needs writing at: " + $f)}'
exit 2
