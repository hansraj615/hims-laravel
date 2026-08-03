<?php

it('returns the versioned health response shape', function () {
    $response = $this->getJson('/api/v1/health');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'service',
                'status',
                'version',
            ],
            'meta',
            'errors',
            'request_id',
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'HIMS API is healthy')
        ->assertJsonPath('data.service', 'hims-backend')
        ->assertJsonPath('data.status', 'ok');
});

it('echoes a caller supplied request id', function () {
    $response = $this->withHeader('X-Request-Id', 'request-123')
        ->getJson('/api/v1/health');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id', 'request-123')
        ->assertJsonPath('request_id', 'request-123');
});
