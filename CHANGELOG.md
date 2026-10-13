# CHANGELOG

## 1.1.4 (2026-10-13)

- answer the `hampel_admin_api_redaction` code event to declare that the add-on has no settings and so no secrets — this adds no dependency, and does nothing on a forum where no add-on defines that event

## 1.1.3 (2026-09-28)

- `$xf.itemsThisPage` now includes sticky threads on the first page of a forum — they are shown in addition to the paginated threads, so page 1 was short by the number of stickies
- requires XenForo 2.2.0 or later; earlier releases declared no minimum, and installed on XenForo 2.0 and 2.1 without having been tested there
- the release no longer includes a `.phpunit.cache` directory, which 1.1.1 shipped by mistake

## 1.1.2 (2025-12-12)

- run `enqueuePostUpgradeCleanUp()` during upgrades on XenForo 2.3 and later

## 1.1.1 (2024-07-12)

- code cleanup - no changes in functionality

## 1.1.0 (2020-11-05)

- new templates:
  - xfmg_watched_media
  - whats_new_posts
  - xfmg_whats_new_media
  - xfmg_whats_new_media_comments
  - xfrm_whats_new_resources
  - whats_new_profile_posts
  - news_feed
  - xfrm_watched_resources
  - watched_forums_list
  - xfmg_watched_albums
  - xfmg_watched_categories
  - xfrm_watched_categories
  - latest_activity
  - tag_view
  - search_results

## 1.0.0 (2020-10-07)

- initial working version
