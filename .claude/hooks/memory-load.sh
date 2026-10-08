#!/usr/bin/env bash
# SessionStart: inject the project memory (main.md) and, on a feature branch, its own memory.

root=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --show-toplevel 2>/dev/null) || exit 0
branch=$(git -C "$root" symbolic-ref --short HEAD 2>/dev/null) || exit 0

context=""
for b in main "$branch"; do
  file="$root/.claude/memories/$b.md"
  [ -f "$file" ] || continue
  context+="Memory for branch $b (.claude/memories/$b.md):"$'\n'"$(cat "$file")"$'\n\n'
  [ "$b" = "$branch" ] && break
done
[ -n "$context" ] || exit 0

jq -n --arg c "$context" '{hookSpecificOutput: {hookEventName: "SessionStart", additionalContext: $c}}'
