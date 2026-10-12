# Testing Items This Page

What this add-on touches, what breaks quietly, and which checks a machine can settle. Routes are
given as routes, not URLs — substitute your own board.

## Surfaces

### Code event listeners

| event | callback | does |
|---|---|---|
| `templater_global_data` | `Listener::templaterGlobalData` | sets `$xf.itemsThisPage` on public pages rendered from a `View` reply whose template is in its switch |
| `hampel_admin_api_redaction` | `Listener::adminApiRedaction` | declares that the add-on has no secret settings; fires only where an add-on defining that event is installed |

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
page never gets it until it is added. `SwitchTemplatesTest` and the release check under *Automated*
catch the rename; nothing catches the new page.

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
vendor/bin/phpunit                                  # 65 tests; some may skip, see below
vendor/bin/phpunit --order-by=random                # must also pass
vendor/bin/phpunit --filter RouteTest --display-skipped
```

- `ListenerTest` — the `itemsThisPage()` arithmetic: empty, partial, exact and overflowing pages.
- `TemplateSwitchTest` — the listener called with `View` replies built in memory: one case per
  group of templates, sticky threads on page 1 and their absence after it, every collection
  template, `news_feed` with an empty array, and What's New with an in-memory `FindNew`. Three
  guards must leave the variable unset — a non-public app, a reply that is not a view, and an
  unknown template — each beside a positive control, so a case that never ran cannot pass.
- `RouteTest` — dispatches real pages through XenForo's router and controllers, then asks XenForo
  for the reply's global template data, so `templater_global_data` fires as it does for a page. It
  never calls the listener, which makes it the test of the listener's registration as well as of
  the view params it reads: a renamed param fails it with the `ErrorException` a live page throws.
  The count must equal the list the reply carries. It covers 32 of the 39 templates:
  - forum pages over two pages, with a thread made sticky for one test: counted on page 1 only;
  - thread pages, and an article thread, whose pinned first post is not counted on page 2;
  - an article forum, the member list, current visitors, latest activity, the news feed, new posts
    and profile posts, watched threads and forums, find threads, a search, and a tag page;
  - Media Gallery and Resource Manager: index, albums, a member's media and albums, a category, an
    author, latest reviews, What's New and the watched lists, where those add-ons are installed.

  It runs inside a database transaction: viewing a thread logs a view, and a search, a What's New
  page, a tag and the sticky flag all write rows.
  `test_every_template_in_the_switch_is_accounted_for` requires each template in the switch to be
  dispatched or listed in `NOT_DISPATCHED` with a reason.
- `SwitchTemplatesTest` — every template named in the listener's switch exists among the master
  templates of the XenForo, Media Gallery and Resource Manager installed. It reads the names out of
  `Listener.php`, so a new case is covered automatically. This is the test that catches a renamed
  template, which nothing else reports: the page keeps rendering and loses the variable.
- `SecretsDeclarationTest` — the secrets declaration names this add-on only, every secret it lists
  is an option the add-on has, any option that reads like a credential is declared or decided, and
  no `config.php` key is claimed.

**`RouteTest` skips, with a stated reason, where the install lacks the data** — a discussion forum
with at least three threads, a discussion thread with at least two replies, an article forum and an
article thread with a reply, a thread to search for and to tag, and Media Gallery or Resource
Manager for their pages. **Where those two are installed and empty, their tests pass on a count of
0 against an empty list**: that proves the view params exist under the names the listener reads,
and nothing more.
**`SwitchTemplatesTest` skips its Media Gallery or Resource Manager test where that add-on is not
installed.** A skip is untested, not passed: read the skip list.

**`TemplateSwitchTest` has to make the app public.** The framework boots `XF\App`, and the
listener acts only on `XF\Pub\App`; without `setAppClassType('public')` in `setUp()` every case
leaves the variable unset.

**Mutation checks, run when each test was added.** Removing the sticky-thread addition, removing the
public-app guard, or adding one to the What's New count each fails exactly the test aimed at it.
Renaming `totalPosts` or the forum's `total` in the listener fails `RouteTest` with the same
`ErrorException` a live page would throw, and so does misreading `totalItems` or `totalResults`.
Renaming the listener method, so that the registration points at nothing, fails every `RouteTest`
dispatch; adding one to the search count, or ignoring `watchedForums`, fails that page's test; a new
`case` in the switch fails the accounting test. For `SecretsDeclarationTest`: declaring a second
add-on's id, declaring an option that does not exist, claiming a `config.php` key, and adding an
undeclared option whose name contains `ApiKey` each fail one test, a different one each time;
renaming its method fails the test that has XenForo fire the event. Copy the file aside and back to
repeat one — `git checkout` would also discard uncommitted work.

### A new XenForo release, before installing it

**`SwitchTemplatesTest` only knows the versions installed where it runs.** To check a release that
is not installed anywhere yet, count the switch's template names against the master templates
inside its zip: each full-release zip of XenForo, Media Gallery and Resource Manager carries them
at `upload/src/addons/<XF|XFMG|XFRM>/_data/templates.xml`. Run from the add-on's root:

```python
import re, sys, zipfile, xml.etree.ElementTree as ET

# usage: python3 check.py XF|XFMG|XFRM path/to/full-release.zip
product, path = sys.argv[1], sys.argv[2]

names = sorted(set(re.findall(r"case '([a-z_]+)'", open('Listener.php').read())))
prefix = {'XFMG': 'xfmg_', 'XFRM': 'xfrm_'}
mine = [n for n in names if (n.startswith(prefix[product]) if product in prefix
                             else not n.startswith(('xfmg_', 'xfrm_')))]

zf = zipfile.ZipFile(path)
entry = [n for n in zf.namelist() if n.endswith(product + '/_data/templates.xml')][0]
have = {t.get('title') for t in ET.fromstring(zf.read(entry)).iter('template')
        if t.get('type') == 'public'}

missing = [n for n in mine if n not in have]
print(f'{product}: {len(mine) - len(missing)}/{len(mine)}', 'missing: ' + ', '.join(missing) if missing else '')
```

Expect 20/20 for XenForo, 11/11 for Media Gallery and 8/8 for Resource Manager. **Run it against an
older release as well, as the control**: XenForo 2.1 reports 13/20 and Resource Manager 2.1 reports
7/8, missing the thread-type and forum-type templates that 2.2 added, which is what shows the count
can fail. Checked for 1.1.4 against XenForo 2.2.0 to 2.3.13, Media Gallery 2.1.9 to 2.3.13 and
Resource Manager 2.2.0 to 2.3.13: every name present in all of them.

### The rendered page, in a disposable install

**The suite checks the number the listener computes; this checks the number a page prints**, on
Media Gallery and Resource Manager pages the suite cannot dispatch, and after an upgrade from the
published release rather than on a working copy. It writes threads, a resource, two options and a
template modification, so run it only on a throwaway XenForo with development mode off.

1. Install the last published release from its zip, then upgrade to the new one from its zip. The
   upgrade output must say `Importing add-on data`, not `All data imported`. Run the job queue.
2. As the admin, create in forum 2 one sticky thread, six normal threads, and a thread with four
   replies; one resource in a new Resource Manager category. Set `discussionsPerPage` and
   `messagesPerPage` to 2.
3. Add a public template modification on `PAGE_CONTAINER`: `str_replace`, find `</body>`, replace
   `<!--ITP[{$xf.itemsThisPage}]-->$0`. Run the job queue so the template recompiles.
4. Fetch each route as a guest and read the number between `ITP[` and `]`.

| route | expect |
|---|---|
| `forums/2/`, `page-2`, `page-3` | 3, 2, 2 — the sticky counts on page 1 only |
| the four-reply thread, pages 1–3 | 2, 2, 1 |
| `online/` | 1 |
| `resources/` | 1 |
| `media/` | 0 — an empty gallery still passes its params |
| `whats-new/posts/` | 2 |
| `help/` | empty — not in the switch, so nothing is printed |

Then `xf_error_log` must hold nothing from the add-on, and no page may contain `Fatal error:`,
`Stack trace:` or `An exception occurred`.

Run for 1.1.3 on XF 2.2.19 with XFMG 2.2.6 and XFRM 2.2.5, and on XF 2.3.13 with XFMG and XFRM
2.3.13, upgrading from 1.1.1: exactly the expected numbers on both, and nothing logged.

## Needs a human

The disposable-install check above covers the upgrade and the pages it lists. Still unchecked:

- [ ] An article or question thread's second page, where the pinned first post is shown again —
      the count is expected to leave it out (see *Fragile points*); confirm that is still true.
- [ ] `search/` results — a search is a POST, so the check above does not reach it.
- [ ] A Media Gallery page with media in it — the check above only proves the empty case.
