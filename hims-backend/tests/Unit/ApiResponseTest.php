<?php

use App\Support\ApiResponse;
use Illuminate\Http\Request;

it('builds a successful API envelope', function () {
    $request = Request::create('/api/v1/health');
    $request->attributes->set('request_id', 'unit-request');

    $response = ApiResponse::success($request, ['status' => 'ok'], 'Done');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toMatchArray([
            'success' => true,
            'message' => 'Done',
            'data' => ['status' => 'ok'],
            'errors' => null,
            'request_id' => 'unit-request',
        ]);
});
