=== Init Review System – Reactions, Multi-Criteria, Guest-Friendly ===
Contributors: brokensmile.2103  
Tags: review, rating, vote, schema, multi-criteria  
Requires at least: 5.5  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 1.6
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Fast 5-star rating plugin with schema, REST API, shortcode control, localStorage voting. Now with multi-criteria review support.

== Description ==

**Init Review System** adds a clean and customizable 5-star rating system to your WordPress site. Votes are stored via REST API, tracked with `localStorage`, and the average score is auto-calculated and optionally displayed with schema markup.

Built to be lightweight, developer-friendly, and easy to integrate into any theme or custom UI.

This plugin is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of minimalist, fast, and developer-focused tools for WordPress.

GitHub repository: [https://github.com/brokensmile2103/init-review-system](https://github.com/brokensmile2103/init-review-system)

**Highlights:**

- 5-star voting via frontend
- **NEW: Multi-criteria review support**
- Average score display
- Optional login requirement
- Optional strict IP checking
- REST API for vote submission
- JSON-LD schema for SEO
- Works with any post type
- Minimal, theme-friendly UI

== Features ==

- 5-star rating system
- Multi-criteria review scoring (up to 5 custom criteria)
- Shortcode-based integration
- Auto-insert blocks before/after content or comments
- Optional login + IP check to prevent abuse
- REST API endpoint: `/wp-json/initrsys/v1/vote`
- Developer filters and extensible architecture
- No jQuery, only minimal assets loaded when needed

== Usage ==

=== [init_review_system] ===
Displays interactive 5-star voting block.

Attributes:
- `id`: Post ID (default: current post)
- `class`: Custom CSS class
- `schema`: `true|false` – Output JSON-LD schema markup

=== [init_review_score] ===
Displays average score (read-only).

Attributes:
- `id`: Post ID (default: current post)
- `icon`: `true|false` – Show star icon (default: false)
- `sub`: `true|false` – Show `/5` subtext (default: true)
- `class`: Custom CSS class
- `hide_if_empty`: `true|false` – Hide if no reviews (default: false)

=== [init_review_criteria] ===
Displays multi-criteria review block.

Attributes:
- `id`: Post ID (default: current post)
- `class`: Custom CSS class
- `schema`: `true|false` – Output schema markup (default: false)
- `per_page`: Number of reviews to show (default: 0 = all)

== Filters for Developers ==

This plugin provides filters and actions to let developers customize auto-insert behavior, schema output, review permissions, and REST API logic.

**`init_plugin_suite_review_system_auto_insert_enabled_score`**  
Enable or disable automatic score output (before/after content).  
**Applies to:** Frontend filter  
**Params:** `bool $enabled`, `string $position`, `string $post_type`

**`init_plugin_suite_review_system_auto_insert_enabled_vote`**  
Enable or disable automatic voting block insertion.  
**Applies to:** Frontend filter  
**Params:** `bool $enabled`, `string $position`, `string $post_type`

**`init_plugin_suite_review_system_default_score_shortcode`**  
Change the default shortcode used for score auto-insertion.  
**Applies to:** Frontend  
**Params:** `string $shortcode`

**`init_plugin_suite_review_system_default_vote_shortcode`**  
Change the default shortcode used for voting block auto-insertion.  
**Applies to:** Frontend  
**Params:** `string $shortcode`

**`init_plugin_suite_review_system_require_login`**  
Force login for submitting reviews, even if disabled in settings.  
**Applies to:** REST `/submit-criteria-review`  
**Params:** `bool $require_login`

**`init_plugin_suite_review_system_schema_type`**  
Customize schema.org type (e.g., `Book`, `Product`, `Course`).  
**Applies to:** Shortcode output  
**Params:** `string $type`, `string $post_type`

**`init_plugin_suite_review_system_schema_data`**  
Modify JSON-LD schema output array.  
**Applies to:** Shortcode output  
**Params:** `array $data`, `int $post_id`, `string $schema_type`

**`init_plugin_suite_review_system_after_vote`**  
Run custom logic after a single-star vote is submitted.  
**Applies to:** REST `/vote`  
**Params:** `int $post_id`, `float $score`, `float $avg_score`, `int $total_votes`

**`init_plugin_suite_review_system_after_criteria_review`**  
Trigger custom logic after a multi-criteria review is submitted.  
**Applies to:** REST `/submit-criteria-review`  
**Params:** `int $post_id`, `int $user_id`, `float $avg_score`, `string $review_content`, `array $scores`

**`init_plugin_suite_review_system_get_reaction_types`**  
Customize available reaction types (labels + emojis).  
**Applies to:** Reactions system  
**Params:** `array $types`

**`init_plugin_suite_review_system_reaction_meta_key`**  
Customize the meta key used for storing reaction counts.  
**Applies to:** Reaction counts storage  
**Params:** `string $meta_key`, `string $rx_key`

== Screenshots ==

1. **Plugin Settings Page** – Configure general options like login requirement, IP restriction, auto-display position, and up to 5 custom criteria fields.
2. **Single-Star Rating Display** – Star-based rating shown on a post with average score and vote count.
3. **Multi-Criteria Review Display** – Frontend layout showing score breakdown per criteria and full user review content.

== Installation ==

1. Upload plugin to `/wp-content/plugins/`  
2. Activate via Plugins menu  
3. Go to **Settings > Init Review System** to configure options  

== FAQ ==

= Is login required to vote? =  
Not by default. You can enable it in plugin settings.

= Does it support IP protection? =  
Yes. You can enable strict IP check to prevent duplicate votes.

= Can I use this with custom post types? =  
Yes, it works with any post type that uses `the_content()` or `comment_form()`.

= Does it support multi-criteria reviews? =  
Yes. You can define up to 5 custom criteria and show them using the provided shortcode.

= Does it support 10-star ratings? =  
No. The plugin currently supports only a 5-star scale.

== Changelog ==

= 1.6 – September 2, 2025 =
- Reactions endpoint `/reactions/toggle` now **requires login**: enforced `is_user_logged_in()` and nonce validation
- Updated REST API registration: `permission_callback` for `/reactions/toggle` now only allows logged-in users
- Shortcode `[init_reactions]` updated: `require_login` is always `true`, ensuring consistent frontend behavior
- Improved JS logic: 
  - Removed guest/localStorage fallback (no more anonymous reactions)
  - Preserved initial `disabled` state for buttons; guest users always see counts but cannot interact
  - Fixed bug where guest buttons could be re-enabled after API calls
- CSS adjustments: new `.is-disabled` style keeps emoji + counts **fully visible**, while visually indicating login requirement
- Enhanced accessibility: reaction buttons now toggle `aria-pressed` accurately and support `is-active` state for current user reaction
- Code cleanup: removed obsolete guest handling code paths and simplified state management

= 1.5 – September 1, 2025 =
- Enhanced Reactions System with **total reactions counter** displayed under “What do you think?”
- Added unique `id` for total counter span (`irs-total-reactions-{post_id}`) to support JS live updates
- Updated JavaScript: total reaction count now updates instantly when users toggle reactions
- Synced UIkit theme template with total reactions output for consistent frontend display
- Added CSS styles for `.init-reaction-total` (font weight, spacing, responsive display)
- Improved accessibility: total reactions wrapped with `aria-live="polite"` for screen reader updates
- Minor code cleanups and consistency improvements

= 1.4 – August 31, 2025 =
- Introduced **Reactions System**: emoji-based reactions (👍 😄 😍 😯 😠 😢)
- Added shortcode + template for reactions bar
- Reactions stored in both post meta (counts) and dedicated `init_reactions` table (user↔post map)
- Guest-friendly: works without login (tracked via localStorage)
- Added developer filters:
  - `init_plugin_suite_review_system_get_reaction_types`
  - `init_plugin_suite_review_system_reaction_meta_key`
- Internal refactor: extracted reaction-core functions, silent table creation with `dbDelta()`

= 1.3 – August 25, 2025 =
- Restructured admin interface: moved from Settings submenu to dedicated main menu with star icon
- Added comprehensive review management system with bulk operations and filtering capabilities
- Implemented review approval workflow with pending/approved/rejected status management
- Enhanced admin dashboard with review statistics, search functionality, and pagination
- Added individual review actions: approve, reject, delete with proper nonce security
- Integrated bulk actions for managing multiple reviews simultaneously
- Created dedicated review management page with detailed review display and user information
- Improved database queries with proper prepared statements and PHPCS compliance
- Added admin-only review management scripts with proper enqueueing standards
- Refined plugin architecture for better scalability and maintainability

= 1.2 – July 27, 2025 =
- Standardized all output with `esc_html()`, `esc_attr()`, and `wp_kses()` for frontend safety
- Secured REST API endpoint with enforced login + nonce validation for logged-in users
- Added `uninstall.php` to clean up plugin options when uninstalled
- Refined UI labels and descriptions on settings page
- Improved shortcode documentation and attributes in `readme.txt`
- Enhanced multi-criteria input layout for better clarity

= 1.1 – July 11, 2025 =  
- Added support for multi-criteria reviews  
- New shortcode for criteria-based score display  
- Separate logic and schema handling for criteria reviews  
- Improved review interface and modal UX  
- Refactored code to support both single and multi-criteria review paths  

= 1.0 – June 28, 2025 =  
- Initial release  
- Shortcode `[init_review_system]` for 5-star voting block  
- Shortcode `[init_review_score]` for average score display  
- REST API endpoint `/wp-json/initrsys/v1/vote` with conditional login and nonce check  
- Vote tracking via `localStorage` for guest users  
- Optional login restriction + strict IP check using hashed IP + transient  
- JSON-LD schema output using `AggregateRating`  
- Auto-insert system: before/after post content or comment form  
- Admin settings page with shortcode builder and control toggles  
- Minimal, responsive, theme-inheriting design  
- Developer-friendly: filters, actions, and reusable helpers  

== License ==

This plugin is licensed under the GPLv2 or later.  
You are free to use, modify, and distribute it under the same license.
