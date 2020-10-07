<?php namespace Hampel\ItemsThisPage;

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
				case 'member_list':
				case 'online_list':
				case 'xfrm_overview':
				case 'xfrm_category_view':
				case 'xfrm_latest_reviews':
				case 'xfrm_author_view':

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $params['total'], $params['perPage']);
					break;

				case 'xfmg_media_index': // fall through
				case 'xfmg_media_view':
				case 'xfmg_category_view':
				case 'xfmg_album_index':
				case 'xfmg_media_user_index':
				case 'xfmg_album_user_index':

					$data['itemsThisPage'] = self::itemsThisPage($params['page'], $params['totalItems'], $params['perPage']);
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