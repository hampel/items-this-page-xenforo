<?php

namespace Tests;

use Hampel\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	/**
	 * @var string $rootDir path to your XenForo root directory, relative to the addon path
	 *
	 * Set $rootDir to '../../../..' if you use a vendor in your addon id (ie <Vendor/AddonId>)
	 * Otherwise, set this to '../../..' for no vendor
	 *
	 * No trailing slash!
	 */
	protected $rootDir = '../../../..';

	/**
	 * @var array $addonsToLoad an array of XenForo addon ids to load
	 *
	 * Only those addons are loaded, which keeps other addons' class extensions, code event
	 * listeners and Composer autoloading out of these tests.
	 *
	 * XenForo Media Gallery and Resource Manager are loaded too, because the listener handles
	 * their pages and RouteTest dispatches them. They are named here for every test class rather
	 * than only for that one: a class extension cannot be added to a class an earlier test in the
	 * same process has already resolved without it, so widening the list for one class fails in a
	 * full run and passes alone. An id that is not installed matches nothing and is harmless.
	 */
	protected $addonsToLoad = ['Hampel/ItemsThisPage', 'XFMG', 'XFRM'];

	protected function getMockData($file)
	{
		return file_get_contents(__DIR__ . '/mock/' . $file);
	}
}
