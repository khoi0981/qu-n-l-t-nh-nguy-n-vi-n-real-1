````instructions
# AI Agent Instructions for News Web (news_web)

This document is a concise, repo-specific guide so an AI coding agent can be productive quickly.

1) Big picture
- PHP-based news subsite. Public pages live in the repo root (e.g. `index.php`, `news.php`, `detail-page.php`) and serve content from a `news` table in MySQL via PDO (`$pdo` expected).

2) Where to look first
- DB bootstrap: code uses `require __DIR__ . '/../src/config/db.php'`. If that file is missing, create it to provide a PDO $pdo instance.
- Listing & patterns: `index.php` — flexible column detection (INFORMATION_SCHEMA), sorting (`?sort=newest|popular|oldest`), pagination (`?page=`), and `resolve_media_path()`.
- Article view: `news.php` — sets `session_save_path(__DIR__ . '/../tmp')`, fetches a single `news` row, implements file-based comments in `data/comments_<id>.json`, and writes debug info with `error_log()`.

3) Key, discoverable patterns (do not change blindly)
- Flexible schema: functions pick available columns (example: `pick(['title','headline','name'])`). Keep fallback ordering when touching queries.
- Media helpers: `resolve_media_path()` and `resolve_avatar_path()` centralize how images/URLs are resolved; prefer reusing them.
- Comments: stored as JSON files under `../data` (`comments_<id>.json`). Migrate only with an explicit plan.
- Sessions: `news.php` uses a repo-local session folder `../tmp`. Preserve this or update all related code consistently.

4) Runtime & debugging
- Dev environment: XAMPP (Windows). Put project under `htdocs` and open:
  http://localhost/Expense_tracker-main/Expense_Tracker/news_web/
- Debugging: `news.php` prints a debug panel and uses `error_log()` extensively — helpful for local troubleshooting.

5) Security and safe edits
- Many DB operations use PDO, but `index.php` composes SELECT lists and ORDER BY from detected column names. Avoid introducing user-derived SQL. Use prepared statements for runtime parameters (ids, search terms, pagination values).
- Output escaping: excerpts often use `strip_tags()`; templates use `htmlspecialchars()` for fields. Preserve or improve escaping when editing templates.

6) Integration points & assets
- Frontend assets: `css_news/`, `img/`, and `lib/` (owlcarousel, animate, waypoints). Many templates use absolute web paths that include `/Expense_tracker-main/Expense_Tracker/news_web/` — update them consistently if you move the site.

7) Files to reference for concrete examples
- `index.php` — column-detection, pagination UI, `resolve_media_path()`
- `news.php` — session config, single-article fetch, comments handling, debug panel
- `detail-page.php` / `contact.php` — additional templates and small forms

If you want, I can expand this with a minimal `src/config/db.php` example, a short test plan for moving comments into the DB, or a safety checklist for SQL refactors. Which would you like next?
````# AI Agent Instructions for News Web Project

## Project Overview
This is a PHP-based news website template adapted from the Newsers HTML Magazine Template. The project integrates a dynamic news management system with a MySQL database backend.

## Key Architecture Components

### Database Integration
- The project uses PDO for database connections (`../src/config/db.php`)
- News articles are stored in a `news` table with flexible column naming:
  - Primary identifiers: `id` or `news_id`
  - Content fields: `title`, `content`, `excerpt`, `image`
  - Timestamps: `created_at` or `published_at`

### File Structure
```
├── index.php          # Main entry point with news listing
├── news.php           # Individual news article view
├── detail-page.php    # Article detail template
├── css_news/         # Styling assets
└── lib/              # Third-party libraries
    ├── animate/      # Animation effects
    ├── owlcarousel/  # Carousel component
    └── waypoints/    # Scroll animations
```

## Development Workflows

### Local Development
1. Requires XAMPP with PHP and MySQL
2. Place project in `htdocs` directory
3. Access via `http://localhost/Expense_tracker-main/Expense_Tracker/news_web/`

### Key Development Patterns

#### Database Queries
- Uses flexible column detection for different database schemas
- Example from `index.php`:
```php
$cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news'")
```

#### Pagination Implementation
- Articles are paginated with 8 items per page
- URL parameters: `?page=1&sort=newest`

## Integration Points

### Frontend Libraries
1. Bootstrap for responsive layout
2. Owl Carousel for sliding components
3. Waypoints for scroll-based animations

### External Dependencies
- Requires MySQL database with a `news` table
- PHP >= 7.0 with PDO extension enabled

## Common Operations
1. Adding news articles: Insert into the `news` table with required fields
2. Modifying layouts: Edit templates in root directory (*.php files)
3. Styling changes: Modify `css_news/style.css`

## Conventions
1. Use PDO prepared statements for all database queries
2. Follow the existing pagination pattern for list views
3. Maintain responsive design using Bootstrap grid system