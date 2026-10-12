<?php

namespace Tests\Unit;

use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Redirect;

/**
 * Dispatches real pages against the install's own data, asks XenForo for the global template data
 * of the reply it produced, and checks the answer against the items that reply actually carries.
 *
 * Nothing here calls the listener. XenForo fires templater_global_data itself, so these tests fail
 * if the listener is not registered, or is registered with the wrong class, method or signature,
 * as well as when XenForo renames a view param it reads - which throws the same ErrorException a
 * live page would.
 *
 * Each test skips, naming what is missing, on an install without the data it needs. The options and
 * permissions are set in memory. Viewing a thread logs a view and a search or a What's New page
 * saves a result set; the transaction rolls all of it back.
 *
 * Where a page is empty the count is 0 and so is the list, so that case proves the params exist
 * and little else. The comparison is exact, so a page where an item the query returned is then
 * hidden by a permission check would fail it - see TESTING.md's fragile points.
 */
class RouteTest extends TestCase
{
	use UsesDatabaseTransactions;

	private const PERMISSIONS = [
		'general' => [
			'view' => true,
			'viewNode' => true,
			'viewMemberList' => true,
			'viewProfile' => true,
			'search' => true,
		],
		'forum' => ['viewOthers' => true, 'viewContent' => true],
		'profilePost' => ['view' => true],
		'xfmg' => ['view' => true],
		'resource' => ['view' => true],
	];

	/** Templates the tests below dispatch themselves, outside the three data providers. */
	private const DISPATCHED_BY_NAME = [
		'forum_view',
		'forum_view_type_article',
		'thread_view',
		'thread_view_type_article',
		'search_results',
		'tag_view',
		'xfmg_media_user_index',
		'xfmg_album_user_index',
		'xfmg_category_view',
		'xfrm_author_view',
		'xfrm_category_view',
	];

	/**
	 * Templates no test here dispatches, each because the page needs content that cannot be made
	 * inside a rolled-back transaction without recreating what XenForo's own services do.
	 * TemplateSwitchTest covers them with replies built in memory, and TESTING.md's disposable
	 * install check covers them on a rendered page.
	 */
	private const NOT_DISPATCHED = [
		'forum_view_type_question' => 'needs a question forum',
		'forum_view_type_suggestion' => 'needs a suggestion forum',
		'thread_view_type_poll' => 'needs a thread with a poll',
		'thread_view_type_question' => 'needs a thread in a question forum',
		'thread_view_type_suggestion' => 'needs a thread in a suggestion forum',
		'xfmg_media_view' => 'needs a media item',
		'xfrm_thread_view_type_resource' => "needs a resource's discussion thread",
	];

	protected function setUp(): void
	{
		parent::setUp();

		// off by default on some installs, and the pages behind them refuse without them
		$this->setOptions(['enableMemberList' => true, 'enableTagging' => true]);
	}

	protected function actAs($who)
	{
		$visitor = $who == 'member'
			? $this->actingAsMember([], self::PERMISSIONS)
			: $this->actingAsGuest(self::PERMISSIONS);

		foreach (\XF::finder('XF:Node')->fetch() as $node)
		{
			$this->setVisitorContentPermissions($visitor, 'node', $node->node_id, [
				'view' => true,
				'viewOthers' => true,
				'viewContent' => true,
			]);
		}

		return $visitor;
	}

	protected function requireAddOn($addOnId)
	{
		if (!$this->app()->db()->fetchOne('SELECT active FROM xf_addon WHERE addon_id = ?', $addOnId))
		{
			$this->markTestSkipped("{$addOnId} is not installed and active here, so its pages cannot be dispatched");
		}
	}

	/**
	 * The canonical route path, as XenForo builds it: a bare ID redirects to the path with the
	 * title slug, and a redirect carries no view params.
	 */
	protected function routePath($prefix, $entity, $page = 1)
	{
		$link = $this->app()->router('public')->buildLink(
			"nopath:{$prefix}", $entity, $page > 1 ? ['page' => $page] : []
		);

		return ltrim(preg_replace('#^[^?]*\?#', '', $link), '/');
	}

	/**
	 * Dispatches a route, following one redirect: What's New and search both save a result set
	 * and send the visitor on to the page that shows it.
	 */
	protected function page($route, array $input = [], $method = 'GET')
	{
		$reply = $this->dispatch($route, 'public', $input, [], $method);

		if ($reply instanceof Redirect)
		{
			$path = ltrim(preg_replace('#^[^?]*\?#', '', $reply->getUrl()), '/');
			[$route, $query] = array_pad(explode('&', $path, 2), 2, '');
			parse_str($query, $input);

			$reply = $this->dispatch($route, 'public', $input);
		}

		return $reply;
	}

	/**
	 * What XenForo itself puts in $xf for this reply, through the templater_global_data event.
	 */
	protected function itemsThisPage(AbstractReply $reply)
	{
		$data = \XF::app()->getGlobalTemplateData($reply);

		$this->assertArrayHasKey('itemsThisPage', $data, 'XenForo did not get itemsThisPage for this reply');
		$this->assertIsInt($data['itemsThisPage']);

		return $data['itemsThisPage'];
	}

	protected function assertCountsList(AbstractReply $reply, $template, $listParam, $label)
	{
		$this->assertReplyIsView($reply);
		$this->assertSame($template, $reply->getTemplateName(), $label);

		$count = $this->itemsThisPage($reply);

		if ($listParam)
		{
			$this->assertSame(count($this->replyParam($reply, $listParam)), $count, $label);
		}

		return $count;
	}

	/**
	 * A forum hides threads older than its default date limit, so an install with old content
	 * would show an empty page. Ask for all of them, as the "show older items" link does.
	 */
	protected function forumPage($forum, $page = 1)
	{
		return $this->dispatch($this->routePath('forums', $forum, $page), 'public', ['no_date_limit' => 1]);
	}

	protected function forumWithThreads($typeId = 'discussion')
	{
		$forum = \XF::finder('XF:Forum')
			->where('forum_type_id', $typeId)
			->where('discussion_count', '>', 2)
			->order('discussion_count', 'DESC')
			->fetchOne();

		if (!$forum)
		{
			$this->markTestSkipped("needs a {$typeId} forum with at least three threads; the install has none");
		}

		return $forum;
	}

	/**
	 * A case added to the listener has to be dispatched here or listed above with its reason, so
	 * the list of what is only tested in memory cannot fall behind the listener.
	 */
	public function test_every_template_in_the_switch_is_accounted_for()
	{
		preg_match_all("/case '([a-z_]+)'/", file_get_contents(dirname(__DIR__, 2) . '/Listener.php'), $matches);
		$switch = array_values(array_unique($matches[1]));
		sort($switch);

		$dispatched = self::DISPATCHED_BY_NAME;
		foreach ([self::corePages(), self::mediaGalleryPages(), self::resourceManagerPages()] as $pages)
		{
			foreach ($pages as $page)
			{
				$dispatched[] = $page[2];
			}
		}

		$this->assertSame(
			[], array_values(array_intersect($dispatched, array_keys(self::NOT_DISPATCHED))),
			'listed as not dispatched, and dispatched'
		);

		$accounted = array_values(array_unique(array_merge($dispatched, array_keys(self::NOT_DISPATCHED))));
		sort($accounted);

		$this->assertSame($switch, $accounted);
	}

	public function test_forum_pages_count_the_threads_shown()
	{
		$forum = $this->forumWithThreads();
		$this->actAs('guest');

		// a small page size, so the forum has a full page and a partial last page
		$this->setOption('discussionsPerPage', intdiv($forum->discussion_count, 2) + 1);

		foreach ([1, 2] as $page)
		{
			$reply = $this->forumPage($forum, $page);
			$this->assertReplyIsView($reply);
			$this->assertSame('forum_view', $reply->getTemplateName());
			$this->assertSame($page, $this->replyParam($reply, 'page'));

			$stickyThreads = $this->replyParam($reply, 'stickyThreads');
			$shown = count($this->replyParam($reply, 'threads')) + ($stickyThreads ? count($stickyThreads) : 0);

			$this->assertGreaterThan(0, $shown, "forum page {$page} rendered no threads");
			$this->assertSame($shown, $this->itemsThisPage($reply), "forum page {$page}");
		}
	}

	public function test_a_sticky_thread_is_counted_on_page_one_only()
	{
		$forum = $this->forumWithThreads();
		$this->actAs('guest');

		$thread = \XF::finder('XF:Thread')
			->where('node_id', $forum->node_id)
			->where('discussion_state', 'visible')
			->where('sticky', 0)
			->order('last_post_date', 'DESC')
			->fetchOne();
		$thread->fastUpdate('sticky', true);

		$perPage = intdiv($forum->discussion_count, 2);
		$this->setOption('discussionsPerPage', $perPage);

		$first = $this->forumPage($forum, 1);
		$this->assertCount(1, $this->replyParam($first, 'stickyThreads'), 'the sticky thread is not on page 1');
		$this->assertCount($perPage, $this->replyParam($first, 'threads'));
		$this->assertSame($perPage + 1, $this->itemsThisPage($first), 'page 1 should count the sticky thread');

		$second = $this->forumPage($forum, 2);
		$this->assertEmpty($this->replyParam($second, 'stickyThreads'), 'page 2 should carry no sticky threads');
		$this->assertSame(count($this->replyParam($second, 'threads')), $this->itemsThisPage($second), 'page 2');
	}

	public function test_thread_pages_count_the_posts_shown()
	{
		$thread = \XF::finder('XF:Thread')
			->where('discussion_state', 'visible')
			->where('discussion_type', 'discussion')
			->where('reply_count', '>', 1)
			->order('reply_count', 'DESC')
			->fetchOne();

		if (!$thread)
		{
			$this->markTestSkipped('needs a visible discussion thread with at least two replies; the install has none');
		}

		$this->actAs('guest');

		// a small page size, so the thread has a full page and a partial last page
		$this->setOption('messagesPerPage', intdiv($thread->reply_count + 1, 2) + 1);

		foreach ([1, 2] as $page)
		{
			$reply = $this->dispatch($this->routePath('threads', $thread, $page));
			$this->assertReplyIsView($reply);
			$this->assertSame('thread_view', $reply->getTemplateName());
			$this->assertSame($page, $this->replyParam($reply, 'page'));

			$shown = count($this->replyParam($reply, 'posts'));

			$this->assertGreaterThan(0, $shown, "thread page {$page} rendered no posts");
			$this->assertSame($shown, $this->itemsThisPage($reply), "thread page {$page}");
		}
	}

	/**
	 * An article keeps its first post pinned above every page. XenForo carries it apart from the
	 * page's posts, and the count is of the page's own share of the thread - so on a later page the
	 * pinned post is not counted, and on page 1 the article itself is.
	 */
	public function test_an_article_thread_does_not_count_its_pinned_first_post_on_later_pages()
	{
		$thread = \XF::finder('XF:Thread')
			->where('discussion_state', 'visible')
			->where('discussion_type', 'article')
			->where('reply_count', '>', 0)
			->order('reply_count', 'DESC')
			->fetchOne();

		if (!$thread)
		{
			$this->markTestSkipped('needs a visible article thread with at least one reply; the install has none');
		}

		$this->actAs('guest');
		$this->setOption('messagesPerPage', 1);

		$first = $this->dispatch($this->routePath('threads', $thread, 1));
		$this->assertSame('thread_view_type_article', $first->getTemplateName());
		$this->assertNotEmpty($this->replyParam($first, 'pinnedPost'));
		$this->assertCount(0, $this->replyParam($first, 'posts'), 'page 1 should hold only the pinned article');
		$this->assertSame(1, $this->itemsThisPage($first), 'page 1 counts the article itself');

		$second = $this->dispatch($this->routePath('threads', $thread, 2));
		$this->assertNotEmpty($this->replyParam($second, 'pinnedPost'), 'the article is not pinned on page 2');
		$this->assertCount(1, $this->replyParam($second, 'posts'));
		$this->assertSame(1, $this->itemsThisPage($second), 'page 2 counts its reply and not the pinned article');
	}

	public function test_an_article_forum_counts_its_threads()
	{
		$forum = \XF::finder('XF:Forum')
			->where('forum_type_id', 'article')
			->where('discussion_count', '>', 0)
			->fetchOne();

		if (!$forum)
		{
			$this->markTestSkipped('needs an article forum with at least one thread; the install has none');
		}

		$this->actAs('guest');

		$reply = $this->forumPage($forum);
		$this->assertSame('forum_view_type_article', $reply->getTemplateName());

		$stickyThreads = $this->replyParam($reply, 'stickyThreads');
		$shown = count($this->replyParam($reply, 'threads')) + ($stickyThreads ? count($stickyThreads) : 0);

		$this->assertGreaterThan(0, $shown);
		$this->assertSame($shown, $this->itemsThisPage($reply));
	}

	public static function corePages()
	{
		// route, visitor, template, the param holding the list shown (or null where there is none)
		return [
			'member list' => ['members/list/', 'guest', 'member_list', 'users'],
			'current visitors' => ['online/', 'guest', 'online_list', null],
			'latest activity' => ['whats-new/latest-activity', 'guest', 'latest_activity', 'newsFeedItems'],
			'new posts' => ['whats-new/posts/', 'guest', 'whats_new_posts', 'threads'],
			'new profile posts' => ['whats-new/profile-posts/', 'guest', 'whats_new_profile_posts', null],
			'news feed' => ['whats-new/news-feed', 'member', 'news_feed', 'newsFeedItems'],
			'watched threads' => ['watched/threads', 'member', 'watched_threads_list', 'threads'],
			'watched forums' => ['watched/forums', 'member', 'watched_forums_list', 'watchedForums'],
			'threads started' => ['find-threads/started', 'member', 'find_threads_list', 'threads'],
			'unanswered threads' => ['find-threads/unanswered', 'member', 'find_threads_list', 'threads'],
		];
	}

	#[DataProvider('corePages')]
	public function test_core_pages($route, $visitor, $template, $listParam)
	{
		$this->actAs($visitor);

		$this->assertCountsList($this->page($route), $template, $listParam, $route);
	}

	public function test_search_results_count_the_results_shown()
	{
		$this->actAs('guest');

		$thread = \XF::finder('XF:Thread')->where('discussion_state', 'visible')->order('thread_id')->fetchOne();
		if (!$thread)
		{
			$this->markTestSkipped('needs a visible thread to search for; the install has none');
		}

		$word = preg_split('/\W+/', $thread->title, -1, PREG_SPLIT_NO_EMPTY)[0];

		$reply = $this->page('search/search', ['keywords' => $word, 'c' => ['title_only' => 1]], 'POST');

		if (!($reply instanceof \XF\Mvc\Reply\View))
		{
			$this->markTestSkipped("a search for '{$word}' found nothing here, so there is no results page to count");
		}

		$count = $this->assertCountsList($reply, 'search_results', 'results', 'search');

		$this->assertGreaterThan(0, $count, 'the search results page showed nothing');
	}

	public function test_a_tag_page_counts_its_results()
	{
		$this->actAs('guest');

		$thread = \XF::finder('XF:Thread')->where('discussion_state', 'visible')->order('thread_id')->fetchOne();
		if (!$thread)
		{
			$this->markTestSkipped('needs a visible thread to tag; the install has none');
		}

		$tagRepo = \XF::repository('XF:Tag');
		$tag = $tagRepo->createTag('items this page probe');
		$tagRepo->modifyContentTags('thread', $thread->thread_id, [$tag->tag_id], []);

		$count = $this->assertCountsList($this->page("tags/{$tag->tag_url}/"), 'tag_view', 'results', 'tag');

		$this->assertSame(1, $count, 'the tagged thread is not on the tag page');
	}

	public static function mediaGalleryPages()
	{
		return [
			'media index' => ['media/', 'guest', 'xfmg_media_index', 'mediaItems'],
			'album index' => ['media/albums/', 'guest', 'xfmg_album_index', 'albums'],
			'new media' => ['whats-new/media/', 'guest', 'xfmg_whats_new_media', null],
			'new media comments' => ['whats-new/media-comments/', 'guest', 'xfmg_whats_new_media_comments', null],
			'watched media' => ['watched/media', 'member', 'xfmg_watched_media', 'mediaItems'],
			'watched albums' => ['watched/media-albums', 'member', 'xfmg_watched_albums', 'albums'],
			'watched categories' => ['watched/media-categories', 'member', 'xfmg_watched_categories', 'watchedCategories'],
		];
	}

	#[DataProvider('mediaGalleryPages')]
	public function test_media_gallery_pages($route, $visitor, $template, $listParam)
	{
		$this->requireAddOn('XFMG');
		$this->actAs($visitor);

		$this->assertCountsList($this->page($route), $template, $listParam, $route);
	}

	public function test_a_members_media_and_albums()
	{
		$this->requireAddOn('XFMG');
		$this->actAs('guest');

		$user = \XF::finder('XF:User')->where('user_state', 'valid')->order('user_id')->fetchOne();

		$this->assertCountsList(
			$this->page($this->routePath('media/users', $user)), 'xfmg_media_user_index', 'mediaItems', 'media'
		);
		$this->assertCountsList(
			$this->page($this->routePath('media/albums/users', $user)), 'xfmg_album_user_index', 'albums', 'albums'
		);
	}

	public function test_a_media_gallery_category()
	{
		$this->requireAddOn('XFMG');
		$visitor = $this->actAs('guest');

		$category = \XF::finder('XFMG:Category')->where('category_type', 'media')->order('category_id')->fetchOne();
		if (!$category)
		{
			$this->markTestSkipped('needs a Media Gallery category that holds media; the install has none');
		}

		$this->setVisitorContentPermissions($visitor, 'xfmg_category', $category->category_id, ['view' => true]);

		$this->assertCountsList(
			$this->page($this->routePath('media/categories', $category)), 'xfmg_category_view', 'mediaItems', 'category'
		);
	}

	public function test_a_resource_manager_category_and_author()
	{
		$this->requireAddOn('XFRM');
		$visitor = $this->actAs('guest');

		$user = \XF::finder('XF:User')->where('user_state', 'valid')->order('user_id')->fetchOne();

		$this->assertCountsList(
			$this->page($this->routePath('resources/authors', $user)), 'xfrm_author_view', 'resources', 'author'
		);

		$category = \XF::finder('XFRM:Category')->order('resource_category_id')->fetchOne();
		if (!$category)
		{
			$this->markTestSkipped('needs a Resource Manager category; the install has none');
		}

		$this->setVisitorContentPermissions(
			$visitor, 'resource_category', $category->resource_category_id, ['view' => true]
		);

		$this->assertCountsList(
			$this->page($this->routePath('resources/categories', $category)), 'xfrm_category_view', 'resources', 'category'
		);
	}

	public static function resourceManagerPages()
	{
		return [
			'overview' => ['resources/', 'guest', 'xfrm_overview', 'resources'],
			'latest reviews' => ['resources/latest-reviews', 'guest', 'xfrm_latest_reviews', null],
			'new resources' => ['whats-new/resources/', 'guest', 'xfrm_whats_new_resources', 'resources'],
			'watched resources' => ['watched/resources', 'member', 'xfrm_watched_resources', 'resources'],
			'watched categories' => ['watched/resource-categories', 'member', 'xfrm_watched_categories', 'watchedCategories'],
		];
	}

	#[DataProvider('resourceManagerPages')]
	public function test_resource_manager_pages($route, $visitor, $template, $listParam)
	{
		$this->requireAddOn('XFRM');
		$this->actAs($visitor);

		$this->assertCountsList($this->page($route), $template, $listParam, $route);
	}
}
