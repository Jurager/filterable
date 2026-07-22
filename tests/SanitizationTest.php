<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests;

use Jurager\Filterable\Tests\Fixtures\Article;

class SanitizationTest extends TestCase
{
    public function test_sanitizer_transforms_value_before_filtering(): void
    {
        Article::create(['title' => 'Alpha Phone', 'price' => 10]);

        // Article declares 'title' => 'trim' in $sanitizers; without trimming this would not match.
        $titles = Article::query()->filter(['title' => '  Alpha Phone  '])->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }

    public function test_sanitizer_applies_inside_or_groups(): void
    {
        Article::create(['title' => 'Alpha Phone', 'price' => 10]);

        $titles = Article::query()->filter(['or' => ['title' => '  Alpha Phone  ']])->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }

    public function test_invokable_class_sanitizer_is_resolved_through_the_container(): void
    {
        Article::create(['title' => 'Alpha Phone', 'price' => 10, 'status' => 'ACTIVE']);

        // Article declares 'status' => UppercaseSanitizer::class; without resolving and
        // invoking it, this lowercase input would not match the uppercase stored value.
        $titles = Article::query()->filter(['status' => 'active'])->pluck('title');

        $this->assertSame(['Alpha Phone'], $titles->all());
    }
}
