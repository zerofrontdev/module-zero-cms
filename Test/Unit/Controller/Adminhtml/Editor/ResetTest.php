<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Cms\Test\Unit\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Cms\Controller\Adminhtml\Editor\Reset;
use Zero\Cms\Controller\Adminhtml\Editor\Save;
use Zero\Cms\Model\Content\CacheRefresh;
use Zero\Cms\Model\Content\Repository;
use Zero\Cms\Model\Content\Target\Pool;
use Zero\Cms\Model\Content\Target\TypeInterface;
use Zero\Cms\Model\Content\Validator;

/**
 * Reset to All Store Views (and Save) refresh the page in the cache after
 * the write, and only when the write happened.
 */
#[AllowMockObjectsWithoutExpectations]
class ResetTest extends TestCase
{
    private Repository&MockObject $repository;
    private CacheRefresh&MockObject $cacheRefresh;
    private IdentityInterface $entity;
    /** @var array<string, mixed> */
    private array $params = ['type' => 'cms_page', 'id' => '3', 'store' => '2'];
    private ?int $status = null;
    private ?array $answer = null;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(Repository::class);
        $this->cacheRefresh = $this->createMock(CacheRefresh::class);
        $this->entity = new class implements IdentityInterface {
            public function getIdentities()
            {
                return ['cms_p_3'];
            }
        };
    }

    public function testResetRefreshesTheCache(): void
    {
        $calls = [];
        $this->repository->expects($this->once())->method('delete')
            ->with('cms_page', 3, 2, 'jane')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'delete';
            });
        $this->cacheRefresh->expects($this->once())->method('refresh')
            ->with($this->identicalTo($this->entity))
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'refresh';
            });

        $this->reset()->execute();

        $this->assertSame(['delete', 'refresh'], $calls, 'the cache is refreshed after the content is gone');
        $this->assertSame(200, $this->status);
        $this->assertSame(['ok' => true], $this->answer);
    }

    public function testARefusedResetCleansNothing(): void
    {
        $this->repository->method('delete')->willThrowException(new LocalizedException(__('The database is read-only.')));
        $this->cacheRefresh->expects($this->never())->method('refresh');

        $this->reset()->execute();

        $this->assertSame(400, $this->status);
        $this->assertSame(['error' => 'The database is read-only.'], $this->answer);
    }

    public function testAnUnknownTypeCleansNothing(): void
    {
        $this->params['type'] = 'nope';
        $this->repository->expects($this->never())->method('delete');
        $this->cacheRefresh->expects($this->never())->method('refresh');

        $this->reset()->execute();

        $this->assertSame(400, $this->status);
        $this->assertSame(['error' => 'Unknown content type.'], $this->answer);
    }

    public function testSaveStillRefreshesTheCacheAfterItsWrite(): void
    {
        $this->params['content'] = '{"components":[]}';
        $saved = ['components' => []];
        $calls = [];
        $this->repository->expects($this->once())->method('save')
            ->with('cms_page', 3, 2, ['components' => []], 'jane', '')
            ->willReturnCallback(function () use (&$calls, $saved) {
                $calls[] = 'save';
                return $saved;
            });
        $this->repository->method('getRevisions')->willReturn([]);
        $this->cacheRefresh->expects($this->once())->method('refresh')
            ->with($this->identicalTo($this->entity))
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'refresh';
            });

        $this->save()->execute();

        $this->assertSame(['save', 'refresh'], $calls, 'the cache is refreshed after the content is written');
        $this->assertSame(200, $this->status);
        $this->assertSame(['content' => $saved, 'revisions' => [], 'warnings' => []], $this->answer);
    }

    public function testSaveWithNothingPostedCleansNothing(): void
    {
        $this->repository->expects($this->never())->method('save');
        $this->cacheRefresh->expects($this->never())->method('refresh');

        $this->save()->execute();

        $this->assertSame(400, $this->status);
        $this->assertSame(['error' => 'Nothing to save.'], $this->answer);
    }

    private function reset(): Reset
    {
        return new Reset($this->context(), $this->pool(), $this->repository, $this->cacheRefresh);
    }

    private function save(): Save
    {
        $validator = $this->createStub(Validator::class);
        $validator->method('missingRequired')->willReturn([]);

        return new Save($this->context(), $this->pool(), $this->repository, $validator, $this->cacheRefresh);
    }

    private function pool(): Pool
    {
        $handler = $this->createStub(TypeInterface::class);
        $handler->method('load')->willReturn($this->entity);
        $handler->method('title')->willReturn('Home');

        return new Pool(['cms_page' => $handler]);
    }

    private function context(): Context
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(fn ($key, $default = null) => $this->params[$key] ?? $default);

        $json = $this->createStub(Json::class);
        $json->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($json) {
            $this->status = (int) $code;
            return $json;
        });
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->answer = $data;
            return $json;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($json);

        $user = $this->createStub(User::class);
        $user->method('getUserName')->willReturn('jane');
        $auth = $this->createStub(Auth::class);
        $auth->method('getUser')->willReturn($user);

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultFactory')->willReturn($resultFactory);
        $context->method('getAuth')->willReturn($auth);

        return $context;
    }
}
