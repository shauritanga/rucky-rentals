<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeInputCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class NormalizeInputCaseTest extends TestCase
{
    public function test_emails_are_lower_cased_and_vrn_is_upper_cased(): void
    {
        $request = Request::create('/x', 'POST', [
            'email' => '  Jane.Doe@Example.COM ',
            'tenant_email' => 'T@X.com',
            'vrn' => '40-012345-a',
            'vat_number' => 'p051234567m',
            'name' => 'Keep Case',
            'password' => 'KeepMe123',
            'nested' => ['new_tenant_email' => 'N@X.COM'],
        ]);

        (new NormalizeInputCase())->handle($request, fn ($r) => 'ok');

        $this->assertSame('jane.doe@example.com', $request->input('email'));
        $this->assertSame('t@x.com', $request->input('tenant_email'));
        $this->assertSame('40-012345-A', $request->input('vrn'));
        $this->assertSame('P051234567M', $request->input('vat_number'));
        $this->assertSame('Keep Case', $request->input('name'));
        $this->assertSame('KeepMe123', $request->input('password'));
        $this->assertSame('n@x.com', $request->input('nested.new_tenant_email'));
    }

    public function test_phone_numbers_become_international(): void
    {
        $request = Request::create('/x', 'POST', [
            'phone' => '0712 345 678', 'nok_phone' => '255712345678', 'manager_phone' => '+254 722 000 111', 'new_tenant_phone' => '712345678', 'note' => 'call 0712',
        ]);

        (new NormalizeInputCase())->handle($request, fn ($r) => 'ok');

        $this->assertSame('+255712345678', $request->input('phone'));
        $this->assertSame('+255712345678', $request->input('nok_phone'));
        $this->assertSame('+254722000111', $request->input('manager_phone'));
        $this->assertSame('+255712345678', $request->input('new_tenant_phone'));
        $this->assertSame('call 0712', $request->input('note'));
    }
}
