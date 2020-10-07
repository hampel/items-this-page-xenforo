<?php namespace Tests\Unit;

use Hampel\ItemsThisPage\Listener;
use Tests\TestCase;

class ListenerTest extends TestCase
{
    public function testItemsPerPage()
    {
        $this->assertEquals(0, Listener::itemsPerPage(1, 0, 5));
        $this->assertEquals(1, Listener::itemsPerPage(1, 1, 5));
        $this->assertEquals(5, Listener::itemsPerPage(1, 5, 5)); //
        $this->assertEquals(5, Listener::itemsPerPage(1, 6, 5));
        $this->assertEquals(1, Listener::itemsPerPage(2, 6, 5));
        $this->assertEquals(2, Listener::itemsPerPage(2, 7, 5));
        $this->assertEquals(5, Listener::itemsPerPage(2, 10, 5));
        $this->assertEquals(5, Listener::itemsPerPage(2, 11, 5));
    }
}