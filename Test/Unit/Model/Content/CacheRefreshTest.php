<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Cms\Test\Unit\Model\Content;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use PHPUnit\Framework\TestCase;
use Zero\Cms\Model\Content\CacheRefresh;

/**
 * After an editor write, the page or block is cleaned from the page cache
 * (the event Varnish and the built-in cache listen to) and the block cache.
 */
class CacheRefreshTest extends TestCase
{
    public function testAnEntityWithIdentitiesIsCleanedEverywhere(): void
    {
        $entity = new class implements IdentityInterface {
            public function getIdentities()
            {
                return ['cms_p_3', 'cms_p_home'];
            }
        };
        $events = $this->createMock(EventManager::class);
        $events->expects($this->once())->method('dispatch')
            ->with('clean_cache_by_tags', ['object' => $entity]);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with(['cms_p_3', 'cms_p_home']);

        (new CacheRefresh($cache, $events))->refresh($entity);
    }

    public function testAnythingElseIsLeftAlone(): void
    {
        $events = $this->createMock(EventManager::class);
        $events->expects($this->never())->method('dispatch');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('clean');

        (new CacheRefresh($cache, $events))->refresh(new \stdClass());
    }
}
