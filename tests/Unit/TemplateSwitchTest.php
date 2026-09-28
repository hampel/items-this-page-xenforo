<?php

namespace Tests\Unit;

use Hampel\ItemsThisPage\Listener;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

/**
 * Calls the listener with View replies built in memory, one per group of templates it handles.
 *
 * The listener only acts on the public app, and the framework boots XF\App, so setUp() makes
 * \XF::app() a public app first - without that, every case would leave the variable unset and the
 * assertions on it would be the only thing to notice.
 */
class TemplateSwitchTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->setAppClassType('public');
	}

	protected function globalData($template, array $params, $app = null, $reply = false)
	{
		$data = [];

		Listener::templaterGlobalData(
			$app ?? \XF::app(),
			$data,
			$reply === false ? new View('XF:Test', $template, $params) : $reply
		);

		return $data;
	}

	protected function itemsThisPage($template, array $params)
	{
		$data = $this->globalData($template, $params);

		$this->assertArrayHasKey('itemsThisPage', $data, "{$template} did not set itemsThisPage");

		return $data['itemsThisPage'];
	}

	protected function collection($count)
	{
		return new ArrayCollection($count ? array_fill(1, $count, 'item') : []);
	}

	public static function paginatedTemplates()
	{
		return [
			'thread, middle page' => ['thread_view', ['page' => 2, 'totalPosts' => 45, 'perPage' => 20], 20],
			'question thread, last page' => ['thread_view_type_question', ['page' => 3, 'totalPosts' => 45, 'perPage' => 20], 5],
			'resource discussion' => ['xfrm_thread_view_type_resource', ['page' => 1, 'totalPosts' => 3, 'perPage' => 20], 3],
			'article forum, last page' => ['forum_view_type_article', ['page' => 3, 'total' => 45, 'perPage' => 20], 5],
			'find threads' => ['find_threads_list', ['page' => 1, 'total' => 45, 'perPage' => 20], 20],
			'member list' => ['member_list', ['page' => 1, 'total' => 45, 'perPage' => 20], 20],
			'online list, exact last page' => ['online_list', ['page' => 2, 'total' => 40, 'perPage' => 20], 20],
			'resource category' => ['xfrm_category_view', ['page' => 1, 'total' => 7, 'perPage' => 20], 7],
			'watched media' => ['xfmg_watched_media', ['page' => 2, 'total' => 21, 'perPage' => 20], 1],
			'media index, last page' => ['xfmg_media_index', ['page' => 3, 'totalItems' => 45, 'perPage' => 20], 5],
			'media comments, none' => ['xfmg_media_view', ['page' => 1, 'totalItems' => 0, 'perPage' => 20], 0],
			'tag, last page' => ['tag_view', ['page' => 2, 'totalResults' => 21, 'perPage' => 20], 1],
			'search, page of results' => ['search_results', ['page' => 2, 'results' => [1, 2, 3, 4, 5, 6, 7], 'perPage' => 20], 7],
			'search, full page' => ['search_results', ['page' => 1, 'results' => range(1, 20), 'perPage' => 20], 20],
		];
	}

	#[DataProvider('paginatedTemplates')]
	public function test_paginated_templates($template, array $params, $expected)
	{
		$this->assertSame($expected, $this->itemsThisPage($template, $params));
	}

	public function test_sticky_threads_are_added_on_page_one()
	{
		$this->assertSame(23, $this->itemsThisPage('forum_view', [
			'page' => 1, 'total' => 45, 'perPage' => 20, 'stickyThreads' => $this->collection(3),
		]));

		$this->assertSame(5, $this->itemsThisPage('forum_view_type_question', [
			'page' => 1, 'total' => 5, 'perPage' => 20, 'stickyThreads' => $this->collection(0),
		]));
	}

	public function test_sticky_threads_are_null_after_page_one()
	{
		$this->assertSame(5, $this->itemsThisPage('forum_view', [
			'page' => 3, 'total' => 45, 'perPage' => 20, 'stickyThreads' => null,
		]));
	}

	public function test_collection_templates_count_the_collection()
	{
		$cases = [
			'watched_forums_list' => 'watchedForums',
			'xfmg_watched_albums' => 'albums',
			'xfmg_watched_categories' => 'watchedCategories',
			'xfrm_watched_categories' => 'watchedCategories',
			'news_feed' => 'newsFeedItems',
			'latest_activity' => 'newsFeedItems',
		];

		foreach ($cases as $template => $param)
		{
			$this->assertSame(4, $this->itemsThisPage($template, [$param => $this->collection(4)]), $template);
		}
	}

	public function test_news_feed_following_nobody_is_zero()
	{
		// XenForo passes a plain empty array rather than a collection when the visitor follows nobody
		$this->assertSame(0, $this->itemsThisPage('news_feed', ['newsFeedItems' => []]));
	}

	public function test_whats_new_templates_use_the_find_new_result_count()
	{
		$findNew = $this->makeEntity('XF:FindNew', ['results' => range(1, 25)]);
		$this->assertSame(25, $findNew->result_count);

		$templates = [
			'whats_new_posts',
			'whats_new_profile_posts',
			'xfmg_whats_new_media',
			'xfmg_whats_new_media_comments',
			'xfrm_whats_new_resources',
		];

		foreach ($templates as $template)
		{
			$this->assertSame(5, $this->itemsThisPage($template, [
				'page' => 2, 'findNew' => $findNew, 'perPage' => 20,
			]), $template);
		}
	}

	public function test_nothing_is_set_outside_the_public_app()
	{
		$params = ['page' => 1, 'total' => 45, 'perPage' => 20];

		$this->assertSame(20, $this->itemsThisPage('member_list', $params));
		$this->assertArrayNotHasKey('itemsThisPage', $this->globalData('member_list', $params, $this->app()));
	}

	public function test_nothing_is_set_for_a_reply_that_is_not_a_view()
	{
		$this->assertArrayNotHasKey('itemsThisPage', $this->globalData('member_list', [], null, new Redirect('/')));
		$this->assertArrayNotHasKey('itemsThisPage', $this->globalData('member_list', [], null, null));
	}

	public function test_nothing_is_set_for_an_unknown_template()
	{
		$params = ['page' => 1, 'total' => 45, 'perPage' => 20];

		$this->assertSame(20, $this->itemsThisPage('member_list', $params));
		$this->assertArrayNotHasKey('itemsThisPage', $this->globalData('member_list_unknown', $params));
	}
}
