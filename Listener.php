<?php namespace Hampel\ItemsThisPage;

use XF\Entity\FindNew;
use XF\Mvc\Entity\ArrayCollection;

class Listener
{
	public static function templaterGlobalData(\XF\App $app, array &$data, $reply)
	{
		if ($app instanceof \XF\Pub\App && $reply && $reply instanceof \XF\Mvc\Reply\View)
		{
			$params = $reply->getParams();
			$template = $reply->getTemplateName();

			switch ($template)
			{
				case 'thread_view': // fall through
				case 'thread_view_type_article':
				case 'thread_view_type_poll':
				case 'thread_view_type_question':
				case 'thread_view_type_suggestion':
				case 'xfrm_thread_view_type_resource':

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $params['totalPosts'], $params['perPage']);
					break;

				case 'forum_view': // fall through
				case 'forum_view_type_article':
				case 'forum_view_type_question':
				case 'forum_view_type_suggestion':
				case 'find_threads_list':
				case 'watched_threads_list':
				case 'xfmg_watched_media':
				case 'member_list':
				case 'online_list':
				case 'xfrm_overview':
				case 'xfrm_category_view':
				case 'xfrm_latest_reviews':
				case 'xfrm_author_view':
				case 'xfrm_watched_resources':

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $params['total'], $params['perPage']);
					break;

				case 'watched_forums_list':

					$data['itemsThisPage'] = $params['watchedForums'] instanceof ArrayCollection ? $params['watchedForums']->count() : 0;
					break;

				case 'xfmg_media_index': // fall through
				case 'xfmg_media_view':
				case 'xfmg_category_view':
				case 'xfmg_album_index':
				case 'xfmg_media_user_index':
				case 'xfmg_album_user_index':

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $params['totalItems'], $params['perPage']);
					break;

				case 'xfmg_watched_albums':

					$data['itemsThisPage'] = $params['albums'] instanceof ArrayCollection ? $params['albums']->count() : 0;
					break;

				case 'xfmg_watched_categories': // fall through
				case 'xfrm_watched_categories':

					$data['itemsThisPage'] = $params['watchedCategories'] instanceof ArrayCollection ? $params['watchedCategories']->count() : 0;
					break;

				case 'whats_new_posts': // fall through
				case 'xfmg_whats_new_media':
				case 'xfmg_whats_new_media_comments':
				case 'xfrm_whats_new_resources':
				case 'whats_new_profile_posts':

					/** @var FindNew $findNew */
					$findNew = $params['findNew'];

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $findNew->getResultCount(), $params['perPage']);
					break;

				case 'news_feed':

					$data['itemsThisPage'] = $params['newsFeedItems'] instanceof ArrayCollection ? $params['newsFeedItems']->count() : 0;
					break;

				default:
					break;
			}
		}
	}

	public static function itemsThisPage($page, $total, $perPage)
	{
		if ($total == 0)
		{
			return 0;
		}

		$totalPages = intval(ceil($total / $perPage));

		if ($page < $totalPages)
		{
			return intval($perPage);
		}
		else
		{
			if ($total % $perPage == 0)
			{
				return intval($perPage);
			}
			else
			{
				return $total % $perPage;
			}
		}
	}
}