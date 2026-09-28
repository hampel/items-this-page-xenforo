# Page item count for XenForo templates

A XenForo add-on that adds a template variable, `$xf.itemsThisPage`, holding how many items the
current page shows — posts on a thread page, threads on a forum page, media, resources, members and
so on. Templates and template modifications can then depend on how full the page is, which the
page number and per-page setting alone do not tell them on the last page.

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Requirements

- XenForo 2.2.0 or later
- XenForo Media Gallery and XenForo Resource Manager are optional; their pages are covered when
  they are installed

## Usage

**`$xf.itemsThisPage` is available in every template rendered for a covered page**, including
`PAGE_CONTAINER`, so it works in the page's own template and in the page wrapper around it:

```html
<xf:if is="$xf.itemsThisPage >= 5">
    <!-- only on pages showing at least five items -->
</xf:if>
```

**On any other page it is not set at all**, rather than 0. Test for it before using it as a number
where that matters.

## Pages

- Thread pages, including article, poll, question and suggestion threads, and resource discussion
  threads
- Forum pages of every type — page 1 counts sticky threads as well as the paginated ones
- Find threads, watched threads and watched forums
- The member list and the current visitors list
- What's new: posts and profile posts, plus media, media comments and resources where installed
- The news feed and latest activity
- Tag pages and search results
- Media Gallery: the media index, categories, albums, a member's media and albums, a media item's
  comments, and watched media, albums and categories
- Resource Manager: the overview, categories, latest reviews, author pages, and watched resources
  and categories

## How it counts

**The count comes from the totals the page already has, not from the rendered list.** It is exact
in normal use. It can be one high for each item the query returned that the visitor is then not
permitted to see, and it does not include the extra posts an article or question thread pins above
later pages.

## Support

- [Add-on: Items This Page](https://xenforo.com/community/resources/items-this-page.8076/)
- [Discussion and support: Items This Page](https://xenforo.com/community/threads/items-this-page.186224/)
