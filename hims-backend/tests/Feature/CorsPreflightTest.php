<?php

it('allows the local frontend origin to call API routes with request ids', function () {
    $response = $this
        ->withHeaders([
            'Origin' => 'http://127.0.0.1:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'x-request-id,x-requested-with',
        ])
        ->options('/api/v1/health');

    $response
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});
