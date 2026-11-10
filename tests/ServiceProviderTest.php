<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Jurager\Filterable\Tests\Fixtures\Post;

class ServiceProviderTest extends TestCase
{
    public function test_model_retrieval_is_not_hooked(): void
    {
        new Post();

        $this->assertFalse(Event::hasListeners('eloquent.retrieved: ' . Model::class));
        $this->assertFalse(Event::hasListeners('eloquent.retrieved: ' . Post::class));
    }
}
