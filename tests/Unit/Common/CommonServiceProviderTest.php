<?php

namespace Tests\Unit\Common;

use App\Common\Providers\CommonServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CommonServiceProviderTest extends TestCase
{
    #[Test]
    public function test_resolve_provider_class_name_from_file_path(): void
    {
        $app = new \Illuminate\Foundation\Application(dirname(__DIR__, 3));
        $provider = new CommonServiceProvider($app);

        $method = new ReflectionMethod($provider, 'resolveProviderClassName');

        $result = $method->invoke(
            $provider,
            $app->path('Modules/Payment/Providers/PaymentServiceProvider.php')
        );

        $this->assertSame('App\\Modules\\Payment\\Providers\\PaymentServiceProvider', $result);
    }
}
