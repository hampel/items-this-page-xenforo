<?php

namespace Tests\Unit;

use Hampel\ItemsThisPage\Listener;
use Hampel\Testing\Concerns\UsesDatabaseTransactions;
use Tests\TestCase;
use XF\Mvc\Reply\AbstractReply;

/**
 * Dispatches real forum and thread pages against the install's own data, runs the listener on the
 * reply XenForo produced, and checks the answer against the items that reply actually carries.
 *
 * This is the test that fails when XenForo renames a view param the listener reads. It skips,
 * naming what is missing, on an install without the data. The options and permissions it needs
 * are set in memory; viewing a thread logs a view, which the transaction rolls back.
 *
 * The comparison is exact, so a page where a thread or post the query returned is then hidden by
 * a permission check would fail it - see TESTING.md's fragile points.
 */
class RouteTest extends TestCase
{
	use UsesDatabaseTransactions;

	protected function visitorCanSee($nodeId)
	{
		$visitor = $this->actingAsGuest(['general' => ['view' => true]]);

		$this->setVisitorContentPermissions($visitor, 'node', $nodeId, [
			'view' => true,
			'viewOthers' => true,
			'viewContent' => true,
		]);
	}

	/**
	 * The canonical route path, as XenForo builds it: a bare ID redirects to the path with the
	 * title slug, and a redirect carries no view params.
	 */
	protected function routePath($prefix, $entity, $page = 1)
	{
		$link = $this->app()->router('public')->buildLink("nopath:{$prefix}", $entity, ['page' => $page]);

		return ltrim(preg_replace('#^[^?]*\?#', '', $link), '/');
	}

	protected function itemsThisPage(AbstractReply $reply)
	{
		$data = [];
		Listener::templaterGlobalData(\XF::app(), $data, $reply);

		$this->assertArrayHasKey('itemsThisPage', $data, 'the listener did not handle this reply');

		return $data['itemsThisPage'];
	}

	public function test_forum_pages_count_the_threads_shown()
	{
		$forum = \XF::finder('XF:Forum')
			->where('discussion_count', '>', 2)
			->order('discussion_count', 'DESC')
			->fetchOne();

		if (!$forum)
		{
			$this->markTestSkipped('needs a forum with at least three threads; the install has none');
		}

		$this->visitorCanSee($forum->node_id);

		// a small page size, so the forum has a full page and a partial last page
		$perPage = intdiv($forum->discussion_count, 2) + 1;
		$this->setOption('discussionsPerPage', $perPage);

		foreach ([1, 2] as $page)
		{
			$reply = $this->dispatch($this->routePath('forums', $forum, $page));
			$this->assertReplyIsView($reply);
			$this->assertSame($page, $this->replyParam($reply, 'page'));

			$stickyThreads = $this->replyParam($reply, 'stickyThreads');
			$shown = count($this->replyParam($reply, 'threads')) + ($stickyThreads ? count($stickyThreads) : 0);

			$this->assertGreaterThan(0, $shown, "forum page {$page} rendered no threads");
			$this->assertSame($shown, $this->itemsThisPage($reply), "forum page {$page}");
		}
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

		$this->visitorCanSee($thread->node_id);

		// a small page size, so the thread has a full page and a partial last page
		$perPage = intdiv($thread->reply_count + 1, 2) + 1;
		$this->setOption('messagesPerPage', $perPage);

		foreach ([1, 2] as $page)
		{
			$reply = $this->dispatch($this->routePath('threads', $thread, $page));
			$this->assertReplyIsView($reply);
			$this->assertSame($page, $this->replyParam($reply, 'page'));

			$shown = count($this->replyParam($reply, 'posts'));

			$this->assertGreaterThan(0, $shown, "thread page {$page} rendered no posts");
			$this->assertSame($shown, $this->itemsThisPage($reply), "thread page {$page}");
		}
	}
}
