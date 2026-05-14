<?php

namespace Tests\Unit;

use App\Support\PublicTicketUrlBuilder;
use Tests\TestCase;

class PublicTicketUrlBuilderTest extends TestCase
{
    public function test_injects_tenant_subdomain_into_dev_host_with_port(): void
    {
        config()->set('app.frontend_url', 'http://residential.test:3000');

        $url = PublicTicketUrlBuilder::build('tnp', 'TK-2026-001');

        $this->assertSame('http://tnp.residential.test:3000/tickets/TK-2026-001', $url);
    }

    public function test_injects_tenant_subdomain_into_prod_https_host(): void
    {
        config()->set('app.frontend_url', 'https://app.nathen.io.vn');

        $url = PublicTicketUrlBuilder::build('tnp', 'TK-2026-002');

        $this->assertSame('https://tnp.app.nathen.io.vn/tickets/TK-2026-002', $url);
    }

    public function test_falls_back_to_base_host_when_subdomain_is_null(): void
    {
        config()->set('app.frontend_url', 'https://app.nathen.io.vn');

        $url = PublicTicketUrlBuilder::build(null, 'TK-2026-003');

        $this->assertSame('https://app.nathen.io.vn/tickets/TK-2026-003', $url);
    }

    public function test_falls_back_to_base_host_when_subdomain_is_empty_string(): void
    {
        config()->set('app.frontend_url', 'http://residential.test:3000');

        $url = PublicTicketUrlBuilder::build('  ', 'TK-2026-004');

        $this->assertSame('http://residential.test:3000/tickets/TK-2026-004', $url);
    }

    public function test_returns_null_when_frontend_url_is_empty(): void
    {
        config()->set('app.frontend_url', '');

        $this->assertNull(PublicTicketUrlBuilder::build('tnp', 'TK-2026-005'));
    }

    public function test_strips_trailing_slash_from_frontend_url(): void
    {
        config()->set('app.frontend_url', 'https://app.nathen.io.vn/');

        $url = PublicTicketUrlBuilder::build('tnp', 'TK-2026-006');

        $this->assertSame('https://tnp.app.nathen.io.vn/tickets/TK-2026-006', $url);
    }
}
