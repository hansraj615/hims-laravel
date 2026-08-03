<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContextResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            app()->instance(TenantContext::class, $this->resolver->resolveForRequest($request, $user));
        }

        return $next($request);
    }
}
