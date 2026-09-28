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
	 */
	protected $addonsToLoad = ['Hampel/ItemsThisPage'];

	protected function getMockData($file)
	{
		return file_get_contents(__DIR__ . '/mock/' . $file);
	}
}
