# Testing Items This Page

What this add-on touches, what breaks quietly, and which checks a machine can settle. Routes are
given as routes, not URLs — substitute your own board.

## Surfaces

### Code event listeners

| event | callback | does |
|---|---|---|
| `templater_global_data` | `Listener::templaterGlobalData` | sets `$xf.itemsThisPage` on public pages rendered from a `View` reply whose template is in its switch |

The variable is computed from the view params the page's controller already passed: page, per page
and a total, a collection to count, or a What's New `FindNew` entity. Templates from XenForo Media
Gallery and XenForo Resource Manager are in the switch, and only ever match where those add-ons are
installed.

### Other artifacts

- No options, phrases, templates, template modifications, class extensions or schema changes.
- `Setup.php` enqueues XF 2.3's post-upgrade clean-up, behind a version check; nothing else.

## Fragile points

**A template the switch does not name gets no variable at all — not zero.** The list is hard-coded,
so a template renamed in a XenForo, XFMG or XFRM release silently stops getting it, and a new list
page never gets it until it is added.

**The view params are read by name, with no existence check.** A missing key is a PHP warning, and
XenForo turns a warning into an `ErrorException` in production as well as in debug mode — so a
param renamed by a XenForo upgrade breaks the page, not just the count. Every key was present on
XF 2.2.19 and 2.3.12, XFMG 2.2.6 and 2.3.5, and XFRM 2.2.5 and 2.3.5 when this was written.

**The count is arithmetic on totals, not a count of what the page renders.** Four consequences, the
first handled and the rest known and accepted:

- Page 1 of a forum shows its sticky threads on top of the paginated threads, and the forum's
  `total` excludes them. The listener adds `stickyThreads` for this; before 1.1.3 page 1 was short
  by the number of stickies.
- `total` is taken before XenForo's per-item permission filter, so on a page where the query
  returned an item the visitor then cannot see, the count is one high per hidden item. The queries
  already exclude most of what a visitor cannot see, so this is rare.
- `find-threads/` does not reject a page number past the end, so such a page renders no threads and
  still reports a count.
- On article and question threads, the pinned first post and highlighted posts shown on later pages
  are fetched separately and are not in `totalPosts`.

**`news_feed` passes a plain empty array when the visitor follows nobody.** The collection check
fails and the variable is 0, which is correct.

## Automated

```bash
composer install
vendor/bin/phpunit                                  # 25 tests; RouteTest may skip, see below
vendor/bin/phpunit --order-by=random                # must also pass
vendor/bin/phpunit --filter RouteTest --display-skipped
```

- `ListenerTest` — the `itemsThisPage()` arithmetic: empty, partial, exact and overflowing pages.
- `TemplateSwitchTest` — the listener called with `View` replies built in memory: one case per
  group of templates, sticky threads on page 1 and their absence after it, every collection
  template, `news_feed` with an empty array, and What's New with an in-memory `FindNew`. Three
  guards must leave the variable unset — a non-public app, a reply that is not a view, and an
  unknown template — each beside a positive control, so a case that never ran cannot pass.
- `RouteTest` — dispatches a real forum and a real thread, two pages each with the page size set so
  the second is partial, runs the listener on XenForo's own reply, and requires the answer to equal
  the threads (plus stickies) or posts that reply carries. This is the test that catches a renamed
  view param. It runs inside a database transaction, because viewing a thread logs a view.

**`RouteTest` skips, with a stated reason, where the install lacks the data** — a forum with at
least three threads, and a visible discussion thread with at least two replies. A skip is
untested, not passed: read the skip list.

**`TemplateSwitchTest` has to make the app public.** The framework boots `XF\App`, and the
listener acts only on `XF\Pub\App`; without `setAppClassType('public')` in `setUp()` every case
leaves the variable unset.

**Mutation checks, run when each test was added.** Removing the sticky-thread addition, removing the
public-app guard, or adding one to the What's New count each fails exactly the test aimed at it.
Renaming `totalPosts` or the forum's `total` in the listener fails `RouteTest` with the same
`ErrorException` a live page would throw. Copy the file aside and back to repeat one —
`git checkout` would also discard uncommitted work.

## Needs a human

The suite checks the number the listener computes, never a rendered page, and dispatches only core
forum and thread pages. On a throwaway board with a template temporarily edited to print
`{$xf.itemsThisPage}`:

- [ ] Page 1 of a forum with sticky threads — the count includes them; page 2 does not.
- [ ] A thread's first and last pages, and an article thread's second page.
- [ ] One page each from Media Gallery and Resource Manager, where installed — the suite covers
      those only with replies built in memory.
- [ ] `whats-new/posts/` and `search/` results — not dispatched by the suite, because both write a
      result set to the database.
- [ ] A page not in the switch prints nothing rather than 0 or an error.
- [ ] Upgrade from the last published release on XF 2.2 and 2.3: no new server error is logged.
