# Adobe Stock Metadata Injector (Private WordPress Plugin)

This plugin is the **authoritative production system** for Adobe Stock metadata.
Node/CLI prototype files are deprecated for production use.

## Scope and Operating Model

- Adobe Stock only.
- Admin-only plugin (`manage_options`).
- Zero manual metadata editing.
- Fail-closed validation.
- Sequential queue processing with lock.
- Batch upload supported (up to 50 images per batch).

## Required AI JSON Schema (strict)

```json
{
  "title": "",
  "description": "",
  "keywords": [],
  "profile": "",
  "categories": {
    "primary": "",
    "secondary": "",
    "confidence_primary": 0.0,
    "confidence_secondary": 0.0
  }
}
```

## Adobe Category Whitelist (server-side enforced)

The primary category must match exactly one of:

- Abstract
- Animals
- Backgrounds and Textures
- Beauty and Fashion
- Buildings and Architecture
- Business
- Drinks
- Education
- Food and Drink
- Graphic Resources
- Healthcare and Medical
- Hobbies and Leisure
- Industrial
- Landscapes
- Lifestyle
- Nature
- Objects
- People
- Religion and Culture
- Science
- Social Issues
- Sports
- Technology
- Transportation
- Travel

Validation behavior:
- Missing primary category => hard fail.
- Non-whitelist primary category => hard fail.
- No fallback category guessing.

## Profiles (auto-detected only)

Profiles:
- medical
- architecture
- food
- general

No manual profile selection.

Profile safety:
- Medical: no diagnosis/disease/treatment-outcome claims.
- Architecture: no location claims unless visible proof.
- Food: no health claims and no marketing wording.

## Metadata Rules

- Title <= 70 chars.
- Description <= 120 chars.
- Keywords final count 40-49.
- Single-word English-style tokens only (`[a-z0-9-]`), lowercase, deduplicated.
- Multi-word keywords are split, never merged.
- No brands, logos, famous people, copyrighted names.
- No `photo`, `image`, `ai generated`.
- Visible facts only; avoid assumptions.

## Internal-only category data

Stored internally per queue job:
- `metadata.profile`
- `metadata.categories.primary_category`
- `metadata.categories.secondary_category`
- `metadata.categories.confidence_primary`
- `metadata.categories.confidence_secondary`

These are **not injected** into image metadata and are **not sent as embedded image fields**.

## Injection Policy

Only these fields are injected:
- title
- description
- keywords

Injection mode:
- ExifTool when available.
- XMP sidecar fallback when ExifTool is unavailable.

## Dashboard

Dashboard is read-only for metadata content.
Admin actions are limited to:
- queue uploads
- inject/skip toggles
- process queue
- clear terminal jobs

## Hostinger / Shared-host Notes

On many shared hosts (including Hostinger plans), `shell_exec()` or `which exiftool` may be restricted, and ExifTool may not be installed.
The plugin handles this gracefully by creating `.xmp` sidecar files when ExifTool is unavailable.
