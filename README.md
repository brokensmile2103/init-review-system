# Init Review System – Lightweight, Multi-Criteria, Guest-Friendly

> Add fast, schema-ready 5-star rating blocks and emoji reactions to any post – with optional login, strict IP check, REST API, and multi-criteria scoring.

**No bloat. Just clean reviews. Built for themes and developers.**

[![Version](https://img.shields.io/badge/stable-v1.10-blue.svg)](https://wordpress.org/plugins/init-review-system/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![Made with ❤️ in HCMC](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F%20in%20HCMC-blue)

## Overview

Init Review System adds a fast and flexible 5-star rating system and emoji-based Reactions to any post, page, or custom content type. Built with REST API, localStorage, shortcode-first design, and support for multi-criteria reviews — it's perfect for both simple blog votes and advanced product scoring.

Votes and reactions are stored using REST, tracked via localStorage (for guests), and can be auto-inserted or embedded via shortcode. Output is schema-ready with `AggregateRating` for SEO, and all components are cleanly theme-compatible.

## Features

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

## Shortcodes

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

### `POST /wp-json/initrsys/v1/react`  
Submit a reaction for a post (emoji).  
Guest-friendly, tracked via localStorage.

### `GET /wp-json/initrsys/v1/get-reactions`  
Fetch reaction counts for a post.

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

## Installation

1. Upload to `/wp-content/plugins/init-review-system`
2. Activate in WordPress admin
3. Go to **Settings > Init Review System** to configure
4. Use the shortcodes wherever you want reviews or reactions

## License

GPLv2 or later — open source, minimal, developer-first.

## Part of Init Plugin Suite

Init Review System is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of blazing-fast, no-bloat plugins made for WordPress developers who care about quality and speed.
