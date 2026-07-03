# 4SLAS CMS — AGENTS.md

## Goal
Complete CMS features: frontend inline editor, AI tools in /0_9/, go-to-top button, repo description.

## Constraints & Preferences
- AI features only in `/0_9/` — main project must not have them
- `4SLASeditor.js` is never modified; new features extend via `front-editor.js`
- `/0_9/`, `/0_95/`, `/4SLASEditor_AI/` are gitignored (not on GitHub)
- time404.ru uses main project (GitHub)
- Credentials stored in macOS Keychain (osxkeychain helper)

## Bugs Fixed (2026-07-02)

### Critical
1. **`src/front-editor.js` — контекстное меню не работало** — слушатель `contextmenu` был внутри `script.onload` (асинхронная загрузка 4SLASeditor.js) и висел на `editorDiv`. Код стандартного меню браузера не отменялся.  
   **Fix:** вынесли `initContextMenu()` на уровень IIFE, слушатель на `document` с делегированием (`editor.contains(e.target)`).
2. **`src/front-editor.js` — команды не выполнялись по клику** — клик по пункту меню уводил фокус с редактора, `editor.focus()` не восстанавливал выделение.  
   **Fix:** сохраняем `Range` при правом клике, восстанавливаем перед выполнением команды.
3. **`src/front-editor.js` — модалки (link/image/code) не работали** — внутри `insertLink()/uploadImage()/insertCodeBlock()` вызывается `_restoreRange(savedRange)`, который НЕ фокусит редактор. `document.execCommand()` требует `document.activeElement === editor`.  
   **Fix:** monkey-patch `SimpleEditor._restoreRange` — после `sel.addRange(range)` вызывает `this.editor.focus()`.
4. **`admin/redirect.php` — `old_url` из GET не подставлялся** — ссылка вида `redirect.php?old_url=%2Fpract%2F...` открывала форму, но поле "Старый URL" было пустым.  
   **Fix:** добавлен `value="<?php echo htmlspecialchars($_GET['old_url'] ?? ''); ?>"` в инпут.
5. **`src/front-editor.css` — не было стилей модалок** — стили `.editor-modal` (позиционирование, фон, поля, кнопки) были только в `admin.css`, который не грузится на фронтенде. Модалки были невидимы (белый квадрат) или не появлялись.  
   **Fix:** скопированы все стили `.editor-modal`, `.editor-color-*`, `.editor-status` из `admin.css` в `front-editor.css`.

### Minor
6. **`src/front-editor.js` — меню уходило под экран** — `window.innerHeight - 400` давало отрицательный y на мобильных, и меню не было видно.  
   **Fix:** `Math.max(0, window.innerHeight - mh - 10)` + `max-height: min(60vh, 480px); overflow-y: auto` + позиционирование с учётом реальной `offsetHeight`.
7. **`src/front-editor.js` — SimpleEditor мог упасть без try/catch** — если конструктор выбрасывал ошибку, `initContextMenu` не вызывался.  
   **Fix:** обёрнут в `try/catch` с `setStatus('❌ Ошибка загрузки редактора', ...)`.

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
- Context menu uses document-level delegation (`document.addEventListener('contextmenu', ...)` with `editor.contains(e.target)`) — работает без привязки к асинхронной загрузке 4SLASeditor.js
- Frontend editor saves via `/api/quick-save.php` — no CSRF (JSON API, same-origin)
- Cut marker is `<div class="fe-cut">` — stored in HTML, no `<!--cut-->` comment
- GitHub API description updated using token from osxkeychain
- `truncateText()` truncates by words, not characters — prevents mid-word cuts
- `article:author` meta tag self-computes in `header.php` (priority: `display_author` → `author_name` → `site_name`)
- Theme approach: `style.css` untouched, all dark overrides in separate `css/theme.css` (`[data-theme="dark"]` selectors) — максимальное разделение, минимальный риск
- FOUC prevention: inline `<script>` in `<head>` before any CSS, sets `data-theme` from `localStorage['fe-theme']`
- Highlight.js: two `<link>` elements (vs.min.css + atom-one-dark.min.css), toggled via `disabled` attribute
- Monkey-patch `SimpleEditor._restoreRange` в `front-editor.js` — после восстановления Range всегда вызывает `editor.focus()`, чтобы `document.execCommand()` в модалках работал (иначе `document.activeElement` не редактор)
- Range сохраняется при правом клике (`sel.getRangeAt(0)`), восстанавливается перед выполнением команды из меню

## Relevant Files
| File | Role |
|------|------|
| `install.sql` | Schema: pages table has display_author, canonical_url |
| `includes/pages.php` | CRUD: createPage/updatePage + ensurePageColumns |
| `admin/page_edit.php` | Admin editor: fields + POST + emoji buttons |
| `admin/post_edit.php` | Admin post editor (no AI in main) |
| `admin/redirect.php` | Redirect form — reads `old_url` from GET |
| `api/quick-save.php` | New: frontend editor save endpoint |
| `src/front-editor.js` | Frontend editor + context menu (document delegation) + cut + monkey-patch _restoreRange |
| `src/front-editor.css` | Context menu styles + modal styles (copied from admin.css) |
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
