# WP Restatify Booking 2.0.1

## What's new

- Added bootstrap hardening for mixed legacy/new deployments.
- Automatically removes legacy Booking Assistant activation entries when both generations coexist.
- Added guarded loading and fallback for the shared migration notice manager include.
- Added redeclare protection for plugin bootstrap class/function paths.

## Why

This prevents activation/runtime fatals in environments where legacy and refactored plugin generations are both present or where deployments are partial/inconsistent.

## Compatibility

- Plugin version: 2.0.1
- WordPress tested up to 6.9
- No manual settings migration required

## Artifact

- wp_restatify-booking-2.0.1.zip
