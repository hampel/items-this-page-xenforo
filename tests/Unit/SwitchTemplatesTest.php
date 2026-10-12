<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Every template the listener names has to exist in the XenForo, Media Gallery and Resource
 * Manager this install runs.
 *
 * A template renamed by a release is the add-on's one silent failure: the page keeps rendering and
 * simply stops getting the variable. This fails instead, on the day the install is upgraded past
 * the rename. It reads the names out of Listener.php, so a new case is covered without touching
 * this file, and the master templates out of the database, so it needs nothing rendered.
 */
class SwitchTemplatesTest extends TestCase
{
	protected function switchTemplates()
	{
		$source = file_get_contents(dirname(__DIR__, 2) . '/Listener.php');

		preg_match_all("/case '([a-z_]+)'/", $source, $matches);

		return array_values(array_unique($matches[1]));
	}

	protected function templatesFor($product)
	{
		return array_values(array_filter($this->switchTemplates(), function ($title) use ($product)
		{
			if (strpos($title, 'xfmg_') === 0)
			{
				return $product == 'XFMG';
			}

			if (strpos($title, 'xfrm_') === 0)
			{
				return $product == 'XFRM';
			}

			return $product == 'XF';
		}));
	}

	protected function missingFrom($product)
	{
		$installed = $this->app()->db()->fetchAllColumn(
			"SELECT title FROM xf_template WHERE style_id = 0 AND type = 'public' AND addon_id = ?",
			$product
		);

		$this->assertNotEmpty($installed, "{$product} has no master templates on this install");

		return array_values(array_diff($this->templatesFor($product), $installed));
	}

	protected function requireAddOn($addOnId)
	{
		$active = $this->app()->db()->fetchOne(
			'SELECT active FROM xf_addon WHERE addon_id = ?',
			$addOnId
		);

		if (!$active)
		{
			$this->markTestSkipped("{$addOnId} is not installed and active here, so its templates cannot be checked");
		}
	}

	public function test_the_switch_was_read()
	{
		$templates = $this->switchTemplates();

		$this->assertGreaterThan(30, count($templates), 'the case names were not found in Listener.php');
		$this->assertContains('thread_view', $templates);
		$this->assertNotEmpty($this->templatesFor('XFMG'));
		$this->assertNotEmpty($this->templatesFor('XFRM'));
	}

	public function test_every_core_template_exists()
	{
		$this->assertSame([], $this->missingFrom('XF'));
	}

	public function test_every_media_gallery_template_exists()
	{
		$this->requireAddOn('XFMG');

		$this->assertSame([], $this->missingFrom('XFMG'));
	}

	public function test_every_resource_manager_template_exists()
	{
		$this->requireAddOn('XFRM');

		$this->assertSame([], $this->missingFrom('XFRM'));
	}
}
