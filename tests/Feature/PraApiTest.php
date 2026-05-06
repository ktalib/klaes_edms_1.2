<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Pra\PraRecordController;
use App\Http\Requests\Pra\PraSearchRequest;
use App\Http\Requests\Pra\PraStoreRequest;
use App\Http\Requests\Pra\PraUpdateRequest;
use App\Models\User;
use App\Services\Pra\PraRecordService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

class PraApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (app()->routesAreCached()) {
            Artisan::call('route:clear');
        }

        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_returns_paginated_payload(): void
    {
        $user = $this->actingAsSuperAdmin();
        $items = [[
            'prop_id' => '1001',
            'mlsFNo' => 'MLS-1001',
            'Mortgagor' => 'Musa Ali',
            'master' => ['prop_id' => '1001'],
        ]];

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($items): void {
            $mock->shouldReceive('search')
                ->once()
                ->andReturn($this->paginator($items, 1, 10, 1));
        });

        $request = $this->makeFormRequest(PraSearchRequest::class, 'POST', [
            'file_numbers' => ['MLS-1001'],
            'per_page' => 10,
        ], $user);

        $response = $controller->search($request);

        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('1001', $payload['data'][0]['prop_id']);
        $this->assertSame('Musa Ali', $payload['data'][0]['parties']['mortgagor']);
    }

    public function test_show_returns_record_payload(): void
    {
        $user = $this->actingAsSuperAdmin();
        $record = [
            'prop_id' => '900',
            'mlsFNo' => 'MLS-900',
            'Mortgagor' => 'Jane Smith',
        ];

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($record): void {
            $mock->shouldReceive('getByPropId')
                ->once()
                ->with('900')
                ->andReturn($record);
        });

        $request = Request::create('/api/pra/v1/records/900', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->show('900');
        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('900', $payload['data']['prop_id']);
        $this->assertSame('Jane Smith', $payload['data']['parties']['mortgagor']);
    }

    public function test_store_creates_record_and_returns_resource(): void
    {
        $user = $this->actingAsSuperAdmin();
        $payload = [
            'transaction_type' => 'Mortgage',
            'mlsFNo' => 'MLS-2002',
            'parties' => ['mortgagor' => 'Alice Tenant'],
        ];

        $record = array_merge($payload, ['prop_id' => '2002', 'Mortgagor' => 'Alice Tenant']);

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($payload, $record, $user): void {
            $mock->shouldReceive('createRecord')
                ->once()
                ->with($payload, $user->id)
                ->andReturn($record);
        });

        $request = $this->makeFormRequest(PraStoreRequest::class, 'POST', $payload, $user);

        $response = $controller->store($request);
        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('Record created successfully', $payload['message']);
        $this->assertSame('2002', $payload['data']['prop_id']);
        $this->assertSame('Alice Tenant', $payload['data']['parties']['mortgagor']);
    }

    public function test_update_returns_validation_errors_from_service(): void
    {
        $user = $this->actingAsSuperAdmin();
        $payload = ['mlsFNo' => 'MLS-999'];
        $exception = ValidationException::withMessages([
            'mlsFNo' => ['Duplicate file number'],
        ]);

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($payload, $user, $exception): void {
            $mock->shouldReceive('updateRecord')
                ->once()
                ->with('1001', $payload, $user->id)
                ->andThrow($exception);
        });

        $request = $this->makeFormRequest(PraUpdateRequest::class, 'PUT', $payload, $user);

        $response = $controller->update($request, '1001');
        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Validation failed', $payload['message']);
        $this->assertSame('Duplicate file number', $payload['errors']['mlsFNo'][0]);
    }

    public function test_history_endpoint_returns_timeline_entries(): void
    {
        $user = $this->actingAsSuperAdmin();
        $history = [[
            'prop_id' => '757',
            'transaction_type' => 'Assignment',
            'Assignor' => 'Legacy Owner',
        ]];

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($history): void {
            $mock->shouldReceive('getHistory')
                ->once()
                ->with('757')
                ->andReturn($history);
        });

        $request = Request::create('/api/pra/v1/records/757/history', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->history($request, '757');
        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('Assignment', $payload['data'][0]['transaction_type']);
        $this->assertSame('Legacy Owner', $payload['data'][0]['parties']['assignor']);
    }

    public function test_duplicates_endpoint_transforms_duplicate_records(): void
    {
        $user = $this->actingAsSuperAdmin();
        $duplicates = [[
            'match_type' => 'file_number_collision',
            'confidence' => 0.8,
            'record' => [
                'prop_id' => '888',
                'Mortgagor' => 'Conflict Holder',
            ],
        ]];

        $controller = $this->controllerWithMock(function (MockInterface $mock) use ($duplicates): void {
            $mock->shouldReceive('getDuplicates')
                ->once()
                ->with('888')
                ->andReturn($duplicates);
        });

        $request = Request::create('/api/pra/v1/records/888/duplicates', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->duplicates($request, '888');
        $payload = $response->getData(true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('file_number_collision', $payload['data'][0]['match_type']);
        $this->assertSame('888', $payload['data'][0]['record']['prop_id']);
        $this->assertSame('Conflict Holder', $payload['data'][0]['record']['parties']['mortgagor']);
    }

    private function paginator(array $items, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => '/api/pra/v1/records/search']);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = $this->makeDetachedUser(['id' => 999, 'type' => 'super admin']);

        return $user;
    }

    private function makeDetachedUser(array $overrides = []): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'id' => random_int(1, PHP_INT_MAX),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid()->toString().'@example.test',
            'password' => 'secret',
            'type' => 'user',
        ], $overrides));

        $user->exists = true;

        return $user;
    }

    private function controllerWithMock(callable $callback): PraRecordController
    {
        $mock = Mockery::mock(PraRecordService::class);
        $callback($mock);

        return new PraRecordController($mock);
    }

    private function makeFormRequest(string $class, string $method, array $payload, User $user)
    {
        $request = $class::createFromBase(Request::create('/', $method, $payload));
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $request->validateResolved();

        return $request;
    }
}
