# Init Review System – Lightweight, Multi-Criteria, Guest-Friendly

> Add fast, schema-ready 5-star rating blocks and emoji reactions to any post – with Block Editor & Abilities API support, optional login, strict IP check, REST API, and multi-criteria scoring.

**No bloat. Just clean reviews. Built for themes and developers.**

[![Version](https://img.shields.io/badge/stable-v2.0.0-blue.svg)](https://wordpress.org/plugins/init-review-system/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![Made with ❤️ in HCMC](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F%20in%20HCMC-blue)

## Overview

Init Review System adds a fast and flexible 5-star rating system and emoji-based Reactions to any post, page, or custom content type. Built with REST API, localStorage, shortcode-first design, and support for multi-criteria reviews — it's perfect for both simple blog votes and advanced product scoring.

Votes and reactions are stored using REST, tracked via localStorage (for guests), and can be auto-inserted, embedded via shortcode, or added natively as Block Editor blocks. Output is schema-ready with `AggregateRating` for SEO, and all components are cleanly theme-compatible.

## What's New in v2.0.0

- **Block Editor (Gutenberg) support**: four dynamic blocks — Review Score, Review Widget, Review Criteria, Reactions Bar — grouped under their own **Init Review System** category in the block inserter. Each block is registered via `block.json` with a PHP `render.php` that calls the exact same shortcode function as its shortcode counterpart, so output never diverges. A single no-build-step vanilla JS file powers the editor integration, with `wp.serverSideRender` for live preview
- **Abilities API support (WordPress 6.9+)**: registers three read-only abilities — `init-review-system/get-review-score`, `init-review-system/get-criteria-reviews`, `init-review-system/get-reactions-summary` — discoverable and executable via PHP, `wp_get_abilities()`, and the `wp-abilities/v1` REST namespace when a site opts in. Actions that write data (vote, submit review, toggle reaction) are intentionally **not** exposed as abilities. Fully optional: on WordPress versions older than 6.9, this silently does nothing
- **Requires at least** raised from 5.5 to 6.9 to support the Abilities API. `Requires PHP` stays at 7.4

## Features

- Block Editor (Gutenberg) blocks: Review Score, Review Widget, Review Criteria, Reactions Bar — each with a live server-side preview in the editor
- Abilities API (WordPress 6.9+): review score, criteria reviews, and reactions summary exposed as discoverable, executable abilities
- 5-star rating block with voting
- Optional average score display (readonly)
- Emoji-based **Reactions System** (👍 😄 😍 😯 😠 😢)
- Guest-friendly: no login required, tracked via localStorage
- Optional login-only voting mode
- Strict IP check mode
- JSON-LD output (`schema.org/AggregateRating`)
- REST API for votes, reactions, and reviews
- Multi-criteria review block (up to 5 criteria)
- Shortcode-based control (`[init_review_system]`, `[init_reactions]`, etc.)
- Auto-insert: before/after content or comment form
- Lightweight, zero jQuery, no frontend bloat
- Developer filters and template overrides

## Block Editor (Gutenberg)

Four dynamic blocks are available under their own **Init Review System** category in the block inserter — no shortcodes needed if you prefer working entirely in the editor:

| Block | Equivalent shortcode |
|---|---|
| **Review Score** | `[init_review_score]` |
| **Review Widget** | `[init_review_system]` |
| **Review Criteria** | `[init_review_criteria]` |
| **Reactions Bar** | `[init_reactions]` |

Each block shares the exact same rendering code as its shortcode, so switching between the Block Editor and shortcodes never changes the output. Block settings map directly to shortcode attributes, and a live preview is shown right in the editor as you configure it.

## Abilities API (WordPress 6.9+)

On WordPress 6.9 and above, Init Review System registers three read-only abilities under the `init-review-system` category:

- `init-review-system/get-review-score` — average score + vote count for a post
- `init-review-system/get-criteria-reviews` — criteria breakdown + a page of written reviews for a post
- `init-review-system/get-reactions-summary` — emoji reaction counts for a post

These are discoverable and executable via PHP (`wp_get_abilities()`), and — for sites that opt in — the `wp-abilities/v1` REST namespace. Write actions (voting, submitting a review, toggling a reaction) are intentionally **not** exposed as abilities, since an ability can be discovered and invoked directly by an AI agent or automation tool — those actions should only ever happen through the visitor's own, in-context interaction. This integration is fully optional: on WordPress versions older than 6.9, it silently does nothing.

## Shortcodes

Prefer working in the Block Editor? Each shortcode below has an equivalent block — see [Block Editor (Gutenberg)](#block-editor-gutenberg) above.

### `[init_review_system]`  
Display interactive 5-star voting block.  

**Attributes:**
- `id`: Post ID (default: current post)
- `class`: Custom wrapper class
- `schema`: `true|false` – enable schema output

---

### `[init_review_score]`  
Display average score only.  

**Attributes:**
- `id`: Post ID
- `icon`: `true|false`
- `sub`: `true|false` – show `/5` subtext
- `hide_if_empty`: `true|false`
- `class`: Custom class

---

### `[init_review_criteria]`  
Display multi-criteria scoring and review form.

**Attributes:**
- `id`: Post ID
- `class`: Custom class
- `schema`: `true|false`
- `per_page`: Number of reviews to show (0 = all)

---

### `[init_reactions]`  
Display emoji-based reactions bar.  

**Attributes:**
- `id`: Post ID (default: current post)
- `class`: Custom class
- `css`: `true|false` – automatically enqueue CSS (default: true)

## REST API Endpoints

### `POST /wp-json/initrsys/v1/vote`  
Submit a single 5-star vote.  
Requires login + nonce if enabled.

### `POST /wp-json/initrsys/v1/submit-criteria-review`  
Submit a multi-criteria review with content.  
Requires login + nonce if enabled.

### `GET /wp-json/initrsys/v1/get-criteria-reviews`  
Fetch multi-criteria reviews for a post.  
Supports pagination (`?page=x&per_page=y`).

### `POST /wp-json/initrsys/v1/reactions/toggle`  
Add, switch, or remove a reaction for a post.  
Requires login + nonce.

### `GET /wp-json/initrsys/v1/reactions/summary`  
Fetch reaction counts (and the current user's reaction, if logged in) for a post.

## Developer Filters

### Auto-insert
- `init_plugin_suite_review_system_auto_insert_enabled_score`
- `init_plugin_suite_review_system_auto_insert_enabled_vote`
- `init_reactions_auto_insert_enabled`
- `init_reactions_auto_insert_atts`

### Shortcode override
- `init_plugin_suite_review_system_default_score_shortcode`
- `init_plugin_suite_review_system_default_vote_shortcode`

### Schema
- `init_plugin_suite_review_system_schema_type`
- `init_plugin_suite_review_system_schema_data`

### Review permissions
- `init_plugin_suite_review_system_require_login`

### After submission
- `init_plugin_suite_review_system_after_vote`
- `init_plugin_suite_review_system_after_criteria_review`

### Reactions
- `init_plugin_suite_review_system_get_reaction_types`
- `init_plugin_suite_review_system_reaction_meta_key`

### Cache TTL
- `init_plugin_suite_review_system_ttl`

## Installation

1. Upload to `/wp-content/plugins/init-review-system`
2. Activate in WordPress admin
3. Go to **Settings > Init Review System** to configure
4. Use the shortcodes, or insert the equivalent blocks in the Block Editor, wherever you want reviews or reactions

## Requirements

- WordPress 6.9 or later (raised from 5.5 in v2.0.0, to support the Abilities API integration)
- PHP 7.4 or later

## License

GPLv2 or later — open source, minimal, developer-first.

## Part of Init Plugin Suite

Init Review System is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of blazing-fast, no-bloat plugins made for WordPress developers who care about quality and speed.
