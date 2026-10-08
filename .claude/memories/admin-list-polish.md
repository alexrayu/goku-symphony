# admin-list-polish

## Status
- 2026-10-08: done, uncommitted. Chapter number shows the public label on index/detail, null cells blank, plural list headings. 30 tests + PHPStan green, checked in browser.

## Gotchas
- EasyAdmin wraps a `formatValue()` string in Twig Markup (rendered raw). Never return user text from it; blank nulls came from overriding the `label/null` template on the dashboard instead.
- EA picks the null template when the *formatted* value is null, so a callable returning '' also hides the badge, but it would unescape the value.

## Open
- Direction column shows the enum name ("Ltr"); readable labels offered to the user, not done.
