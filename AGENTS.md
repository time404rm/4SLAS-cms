# 4SLAS CMS — AGENTS.md

## Goal
Complete CMS features: frontend inline editor, AI tools in /0_9/, go-to-top button, repo description.

## Constraints & Preferences
- AI features only in `/0_9/` — main project must not have them
- `4SLASeditor.js` is never modified; new features extend via `front-editor.js`
- `/0_9/`, `/0_95/`, `/4SLASEditor_AI/` are gitignored (not on GitHub)
- time404.ru uses main project (GitHub)
- Credentials stored in macOS Keychain (osxkeychain helper)

## Bugs Fixed (2026-07-01)

### Critical
1. **`install.sql`** — added `display_author`, `canonical_url` to `CREATE TABLE pages`
2. **`admin/page_edit.php`** — added form fields + POST handler for `display_author`, `canonical_url` + emoji action buttons (💾 save, 💾 save&close, 👁️ preview, ❌ cancel) + `ensurePageColumns()` call
3. **`includes/pages.php`** — `createPage()`/`updatePage()` now accept and persist `$displayAuthor`, `$canonicalUrl`; added `ensurePageColumns()` auto-migration; both return values updated

### Minor
4. **`page.php`** — now uses `$page['canonical_url']` from DB instead of always self-generating
5. **`.gitignore`** — added `src/vendor/`, `src/grapesjs-init.js`

## Features Done (feature/theme-switcher)
- **`css/theme.css`** — new: comprehensive dark mode overrides (~550 строк, все разделы: float-bar, drawer, auth, comments, code blocks, search, tags, share, cookie, emoji-picker, scrollbar, go-to-top, etc.)
- **`assets/highlight/styles/atom-one-dark.min.css`** — new: тёмная тема Highlight.js
- **`templates/header.php`** — FOUC-prevention inline-скрипт в `<head>` (ставит `data-theme` до отрисовки), подключение `css/theme.css`, `id="hljs-theme[-dark]"` на link-ах Highlight.js для переключения через JS
- **`templates/footer.php`** — Theme Switcher JS: `applyTheme()`, `localStorage`, `matchMedia` listener, `fe-theme-btn` click handler, переключение Highlight.js через `disabled`, динамическое обновление `<meta name="theme-color">`
- **Float-bar-bottom** (десктоп) — иконка (пол-солнца / пол-круга), hover-panel с 3 кнопками: ☀️ 🌙 💻
- **Drawer** (мобильные) — секция "Тема" с 3 кнопками

## Key Decisions
- AI features are `/0_9/`-only — main project stays clean
- Context menu calls `_simpleEditor.execCommand()`, not `document.execCommand()`
- Frontend editor saves via `/api/quick-save.php` — no CSRF (JSON API, same-origin)
- Cut marker is `<div class="fe-cut">` — stored in HTML, no `<!--cut-->` comment
- GitHub API description updated using token from osxkeychain
- `truncateText()` truncates by words, not characters — prevents mid-word cuts
- `article:author` meta tag self-computes in `header.php` (priority: `display_author` → `author_name` → `site_name`)
- Theme approach: `style.css` untouched, all dark overrides in separate `css/theme.css` (`[data-theme="dark"]` selectors) — максимальное разделение, минимальный риск
- FOUC prevention: inline `<script>` in `<head>` before any CSS, sets `data-theme` from `localStorage['fe-theme']`
- Highlight.js: two `<link>` elements (vs.min.css + atom-one-dark.min.css), toggled via `disabled` attribute

## Relevant Files
| File | Role |
|------|------|
| `install.sql` | Schema: pages table has display_author, canonical_url |
| `includes/pages.php` | CRUD: createPage/updatePage + ensurePageColumns |
| `admin/page_edit.php` | Admin editor: fields + POST + emoji buttons |
| `admin/post_edit.php` | Admin post editor (no AI in main) |
| `api/quick-save.php` | New: frontend editor save endpoint |
| `src/front-editor.js` | New: frontend editor + context menu + cut |
| `src/front-editor.css` | New: context menu styles |
| `post.php`, `page.php` | Main + /0_9/: $isEditing, $feData, includes |
| `templates/footer.php` | Go-to-top button + Theme Switcher JS |
| `templates/header.php` | FOUC-prevention script, theme.css, hljs links with ids |
| `css/theme.css` | **New:** all `[data-theme="dark"]` overrides |
| `assets/highlight/styles/atom-one-dark.min.css` | **New:** dark highlight.js theme |
| `/0_9/admin/post_edit.php` | Has display_author, canonical_url, emoji buttons |
| `/0_9/admin/page_edit.php` | Has all above + AI SEO + AI перелинковка |
| `/0_9/includes/pages.php` | Same fix as main + auto-migration |
| `README.md` | Updated feature list |

## Next Steps
1. Deploy latest commits to time404.ru
2. Test theme switcher (light/dark/system) on time404.ru
3. Test frontend editor (`?edit=1`) on a real post/page
4. Test context menu right-click on time404.ru
5. Test Markdown import with tables
6. Test anchor scroll under sticky nav
