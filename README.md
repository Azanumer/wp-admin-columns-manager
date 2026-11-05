# Admin Columns Manager

Adds the columns WordPress forgot to the posts and pages list — so you can spot a missing featured image, a thin 200-word post, or a stale page at a glance, without opening each one.

## What it does

| Column | Shows | Sortable |
| ------ | ----- | -------- |
| ID | Post ID | Yes |
| Image | Featured-image thumbnail (48px) | No |
| Words | Word count of the post content | Yes |
| Last Modified | Date + time of the last edit | Yes |
| Comments | Comment count | Yes |

- Settings → Admin Columns toggles each column and picks which public post types get them (posts and pages by default; custom post types included).
- Word-count sorting uses `CHAR_LENGTH(post_content)` as a cheap, correct proxy — longer content sorts above shorter content.

## Installation

1. Upload the plugin folder to `wp-content/plugins/` and activate it.
2. Go to Settings → Admin Columns to toggle columns or post types.

## License

MIT
