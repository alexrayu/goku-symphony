#!/usr/bin/env bash
# SessionStart: inject the current branch memory from .claude/memories into context.

root=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --show-toplevel 2>/dev/null) || exit 0
branch=$(git -C "$root" symbolic-ref --short HEAD 2>/dev/null) || exit 0
file="$root/.claude/memories/$branch.md"
[ -f "$file" ] || exit 0

jq -n --arg b "$branch" --rawfile c "$file" \
  '{hookSpecificOutput: {hookEventName: "SessionStart", additionalContext: ("Memory for branch " + $b + " (.claude/memories/" + $b + ".md):\n" + $c)}}'
