<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_setting_reads_in_one_request_cost_one_query(): void
    {
        SystemSetting::set('alpha', '1');
        SystemSetting::set('beta', '2');

        DB::flushQueryLog();
        DB::enableQueryLog();
        for ($i = 0; $i < 10; $i++) {
            SystemSetting::get('alpha');
            SystemSetting::get('beta');
            SystemSetting::get('missing', 'fallback');
        }

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame('2', SystemSetting::get('beta'));
        $this->assertSame('fallback', SystemSetting::get('missing', 'fallback'));
    }

    public function test_a_changed_setting_is_read_back_straight_away(): void
    {
        SystemSetting::set('gamma', 'old');
        $this->assertSame('old', SystemSetting::get('gamma'));

        SystemSetting::set('gamma', 'new');

        $this->assertSame('new', SystemSetting::get('gamma'));
    }
}
