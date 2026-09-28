# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Items This Page is a XenForo 2 add-on (`Hampel/ItemsThisPage`) that adds a template variable,
`$xf.itemsThisPage`, holding how many posts, threads, media items, resources and so on the current
page is showing. The repository root is the add-on directory, `src/addons/Hampel/ItemsThisPage/`
inside a XenForo install. `README.md` links to the published resource and its support thread.

## The whole add-on is one listener and a hard-coded list of templates

**There are no options, phrases, templates, template modifications or class extensions.** The only
registered piece is a `templater_global_data` code event listener in
`_output/code_event_listeners/`, calling `Listener::templaterGlobalData()`. `Setup.php` does
nothing except enqueue XF 2.3's post-upgrade cleanup, behind an `\XF::$versionId` check because
that method does not exist earlier.

`templaterGlobalData()` acts only on a public-side `View` reply, and switches on the reply's
template name. **A template not named in the switch gets no `itemsThisPage` at all** — not zero —
so a template that core, XFMG or XFRM renames silently stops getting the variable.

### Each group of templates reads different view params

The count is derived from whatever that controller already passed to its view, which is why the
cases are grouped rather than uniform:

| templates | count comes from |
|---|---|
| thread views | `itemsThisPage(page, totalPosts, perPage)` |
| forum views, member and online lists, most XFRM lists | `itemsThisPage(page, total, perPage)`, plus `stickyThreads` on forum page 1 |
| XFMG media and album lists | `itemsThisPage(page, totalItems, perPage)` |
| `whats_new_*` | `itemsThisPage(page, findNew->getResultCount(), perPage)` |
| `tag_view`, `search_results` | `totalResults`, and `count(results)` respectively |
| watched forums, albums and categories, news feed | the `ArrayCollection`'s own `count()`, else 0 |

**The param names are the fragile part.** They are core and XFMG/XFRM controller internals, read
by array key with no existence check, so a param renamed in an XF upgrade raises a PHP warning on
that page rather than quietly returning nothing. To support another page, find the controller
action that renders it, confirm which params it passes, and add its template name to the matching
case — or a new case if none match.

`itemsThisPage($page, $total, $perPage)` is the pure arithmetic: `perPage` on every page but the
last, and the remainder on the last. It is `public static` so the tests can call it directly.

### The floor is XenForo 2.2, and that constrains the code two ways

`addon.json` requires XF 2.2.0+ and declares no PHP version of its own, so XF 2.2's PHP 7.0
minimum applies. Runtime code must therefore:

- **run on PHP 7.0** — no nullable or `void` types, no typed properties, nothing later;
- **name core classes by their pre-2.3 names.** XF 2.3 aliases the old names forward; XF 2.2 has
  no alias for the new ones and fatals.

The floor was undeclared until 1.1.3, so every earlier release installs on any XenForo 2.

## Commands

XF commands run through `cmd.php` at the root of the XenForo install, four levels up:

```bash
php ../../../../cmd.php xf-dev:import --addon=Hampel/ItemsThisPage    # after hand-editing _output/
php ../../../../cmd.php xf-addon:export Hampel/ItemsThisPage          # DB -> _output/, this add-on only
php ../../../../cmd.php xf-addon:build-release Hampel/ItemsThisPage   # release zip into _releases/
```

**Never run `xf-dev:export`** — its `--addon` defaults to every add-on on the install, and it
overwrites `_output/` from the database. `xf-addon:export` is the scoped equivalent, and
`build-release` runs it itself.

### Tests

PHPUnit 12 via `hampel/xenforo-test-framework` ^5.9. Composer is for the tests only — the add-on
has no runtime dependency, and `vendor/` is gitignored. **The suite needs PHP 8.3; that constraint
is `tests/` only.** Runtime code still has the PHP 7.0 floor above.

```bash
composer install                                   # first time
vendor/bin/phpunit                                 # whole suite
vendor/bin/phpunit --filter testItemsPerPage       # one test
```

**Run them from this directory.** The framework boots the enclosing XenForo install through
`tests/TestCase.php`'s relative `$rootDir` of `../../../..`, so the suite needs a working install
around it and fails from anywhere else.

**`$addonsToLoad` limits the boot to this add-on, and the suite does not run without it.** Other
installed add-ons can vendor their own PHPUnit through `composer_autoload`; unisolated, that copy
wins on the class loader and the run dies before the first test with an error about PHPUnit
internals.

There are three test classes, and they prove different things:

- **`ListenerTest`** covers the `itemsThisPage()` arithmetic alone.
- **`TemplateSwitchTest`** calls the listener with `View` replies built in memory, one case per
  group of templates, plus the guards that must leave the variable unset. Its `setUp()` calls
  `setAppClassType('public')`: the framework boots `XF\App`, and without that the listener's
  `instanceof \XF\Pub\App` guard skips every case.
- **`RouteTest`** dispatches real forum and thread pages against the install's data and checks the
  listener's answer against the threads or posts the reply carries. **It is the only test that
  fails when XenForo renames a view param** — with the same `ErrorException` a live page would
  throw. It skips on an install without the data, and runs inside `UsesDatabaseTransactions`
  because viewing a thread logs a view.

XFMG and XFRM pages are covered only by `TemplateSwitchTest`: `$addonsToLoad` keeps those add-ons'
routes out of the suite.

## Release packaging

**Dev-only root files are kept out of two places by two separate mechanisms**, and adding a new one
means updating both:

- **The XenForo release zip** — `build.json` deletes the dev-only files from the build (Composer
  files, `phpunit.xml`, `tests/`, `vendor/`, the PHPUnit caches, `TESTING.md`, both Claude files),
  then **moves every remaining root `*.md` to the zip root**. A new file that must not ship needs
  its own `rm -fv` line placed before that `mv`, or it lands at the zip root where a downloader sees
  it first. **A dot-directory needs one too**: the builder skips an entry whose own name starts with
  a dot, but not the files inside one, so `.phpunit.cache/` is recreated in the zip without it.
- **`git archive`** (GitHub's "Download ZIP") — `export-ignore` entries in `.gitattributes`.

Machine-specific notes go in `CLAUDE.local.md`, which should be gitignored; this file carries
nothing that would not hold for any clone.
