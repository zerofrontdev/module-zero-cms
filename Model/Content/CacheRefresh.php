<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Cms\Model\Content;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * After the editor writes or removes content: as core does on a CMS save,
 * the page, block or post model itself, whose identities the page cache
 * (built-in or Varnish) and the block cache are tagged with, is cleaned.
 */
class CacheRefresh
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly EventManager $eventManager
    ) {
    }

    public function refresh(object $entity): void
    {
        if (!$entity instanceof IdentityInterface) {
            return;
        }
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $entity]);
        $this->cache->clean($entity->getIdentities());
    }
}
