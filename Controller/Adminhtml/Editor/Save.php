<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Cms\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Zero\Cms\Model\Content\CacheRefresh;
use Zero\Cms\Model\Content\Repository;
use Zero\Cms\Model\Content\Validator;
use Zero\Cms\Model\Content\Target\Pool;

/**
 * Saves the editor's content for the page or block and store view (the
 * previous version becomes a revision) and refreshes the page in the full
 * page cache through the entity's cache tags.
 */
class Save extends AbstractEditor implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Pool $targets,
        private readonly Repository $repository,
        private readonly Validator $validator,
        private readonly CacheRefresh $cacheRefresh
    ) {
        parent::__construct($context, $targets);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $target = $this->target();
            $content = $this->postedContent();
            if (!is_array($content)) {
                throw new LocalizedException(__('Nothing to save.'));
            }
            $saved = $this->repository->save(
                $target['type'],
                $target['id'],
                $target['store'],
                $content,
                $this->adminName(),
                (string) $this->getRequest()->getParam('note', '')
            );
            $this->cacheRefresh->refresh($target['entity']);
        } catch (LocalizedException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $warnings = [];
        foreach ($saved['components'] as $component) {
            foreach ($this->validator->missingRequired($component) as $label) {
                $warnings[] = (string) __('%1: "%2" is empty.', $component['type'], $label);
            }
        }

        return $this->json([
            'content' => $saved,
            'revisions' => $this->repository->getRevisions($target['type'], $target['id'], $target['store']),
            'warnings' => $warnings,
        ]);
    }
}
