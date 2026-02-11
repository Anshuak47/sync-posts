# sync-posts

A plugin to sync posts to multiple host sites using WP REST API, along with translations

# WP Post Sync Translator

Real-time WordPress post synchronization plugin using REST API with HMAC key-based authentication and on-target chunked translation via ChatGPT API.

# Overview

WP Post Sync Translator enables a Host WordPress site to push posts in real time to one or more Target WordPress sites.

Communication uses the WordPress REST API.

Authentication is enforced using key + HMAC signature + domain binding.

Translation runs only on the Target site.

Content is translated in chunks to safely handle long posts.

Supports multiple Target sites.

Includes a full audit logging system.

# Features

## Sync Scope

- Post type: post only

- Trigger: On publish and update

- Fields synced:
  - Title

  - Content

  - Excerpt

  - Categories

  - Tags

  - Featured image

## Security

    * Key-based authentication (unique per target)

    * HMAC SHA256 request signing

    * Domain binding validation

    * Constant-time signature comparison

    * No direct DB writes (except custom log table)

## Translation

    * Runs only on Target

    * Language options: French, Spanish, Hindi

    * Uses ChatGPT API key stored on Target

    * Gutenberg block-aware translation

    * Chunked/block-based processing

    * HTML structure preserved

    * Strict prompt enforcement (no markdown, no code wrapping)

## Reliability

    * Deterministic slug updates

    * Dual mapping (Host Post ID + Host Domain)

    * No duplicate posts on update

    * No partial writes on failure

    * Immediate push (no cron jobs)

## Logging

    * Custom log table

    * Logs Host and Target actions

    * Captures:

        * Role

        * Action

        * Host Post ID

        * Target Post ID

        * Target URL

        * Status

        * Error message

        * Time taken

        * Timestamp

    * Admin log viewer with filters and pagination

## Installation & Setup

### Step 1 – Install Plugin

Install and activate the plugin on:

    * Host site

    * Target site

The same plugin is used on both sites.

## Host Site Setup

1. Go to Post Sync → Settings

2. Select Mode: Host

3. Add one or more Target URLs

4. Save

5. Copy the generated key for each target

Each target row:

    * Has a unique auto-generated key

    * Key is non-editable after generation

## Target Site Setup

1. Go to Post Sync → Settings

2. Select Mode: Target

3. Paste the Host-generated key

4. Select translation language

5. Enter ChatGPT API key

6. Save

## How Real-Time Push Works

When a post is published or updated on the Host:

1. Host builds JSON payload.

2. Payload is signed with HMAC SHA256 using target key.

3. Host sends REST request to:

`/wp-json/psync/v1/receive`

4. Target validates:
   - Key

   - HMAC signature

   - Host domain binding

5. Target translates content in Gutenberg block-safe chunks.

6. Target creates or updates mapped post.

7. Taxonomies and featured image are synced.

8. Logs are recorded on both sites.

No cron jobs. No scheduled tasks. Immediate processing.

## Architecture

    * Host Responsibilities

    * Detect publish/update

    * Build payload

    * Sign with HMAC

    * Push to multiple targets

    * Log push result

## Target Responsibilities

    * Validate request

    * Enforce domain binding

    * Translate content

    * Create/update post

    * Map Host ID + Host Domain

    * Sync taxonomies

    * Sideload featured image

    * Log result

## Data Mapping

Each Target post stores:

    * \_psync_host_post_id

    * \_psync_host_domain

This ensures:

    * No collisions across multiple hosts

    * Deterministic updates

    * No duplicate posts

## Translation Strategy

    * Gutenberg blocks parsed via parse_blocks()

    * Only innerHTML translated

    * Block comments preserved

    * Nested blocks handled recursively

    * Strict prompt prevents markdown artifacts

    * Content translated block-by-block

    * Blocks reassembled using serialize_blocks()

## Logs

Custom table:

`wp_psync_logs`

Columns:

    * id

    * role (host/target)

    * action

    * host_post_id

    * target_post_id

    * target_url

    * status

    * message

    * time_taken

    * created_at

Logs accessible via:

### Post Sync → Logs

Filters:

    * Role

    * Status

    * Pagination

## Limits & Known Considerations

    * Translation is synchronous (long posts increase request time).

    * Large posts with many blocks may increase API calls.

    * Hosting environments must allow outbound HTTPS requests.

    * Media sideload depends on remote file accessibility.

    * No scheduled jobs or background queues by design (per spec).

    * Soft delete support is optional and not enabled by default.

    * Only post post type supported.

## Security Considerations

    * Keys are never logged.

    * HMAC verified using hash_equals().

    * Domain header validated.

    * Prepared SQL for logs.

    * Proper sanitization and escaping.

    * Nonces used in admin.

    * Capability checks enforced.

## Testing Instructions

Test using:

    * Two fresh WordPress installs (e.g., host.local, target.local)

    * Create long post (≥ 5,000 characters)

    * Include:

        * Headings

        * Lists

        * Images

        * Multiple categories/tags

    * Publish on Host

    * Verify:

        * Target receives translated content

        * Categories created

        * Featured image sideloaded

        * Logs recorded

        * No duplicates on update
