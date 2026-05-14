<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSnapshot\Jobs\CaptureSlotEndJob;
use App\Modules\PMC\WorkSnapshot\Jobs\CaptureSlotStartJob;
use Database\Seeders\Tenant\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaptureShiftBoundariesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['code' => 'PRJ-TZ']);
        $this->seed(ShiftSeeder::class);
    }

    public function test_timezone_is_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', Carbon::now()->timezone->getName());
    }

    public function test_dispatches_start_job_at_morning_shift_06_00_vn(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-20 06:00:00', 'Asia/Ho_Chi_Minh'));

        $this->artisan('snapshot:capture-shift-boundaries')->assertSuccessful();

        $morning = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();

        Queue::assertPushed(CaptureSlotStartJob::class, fn (CaptureSlotStartJob $job) => $job->shiftId === $morning->id && $job->date === '2026-04-20');

        Carbon::setTestNow();
    }

    public function test_dispatches_end_job_for_overnight_shift_at_06_00_vn_uses_previous_date(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-20 06:00:00', 'Asia/Ho_Chi_Minh'));

        $this->artisan('snapshot:capture-shift-boundaries')->assertSuccessful();

        $overnight = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'TOI')
            ->firstOrFail();

        Queue::assertPushed(CaptureSlotEndJob::class, fn (CaptureSlotEndJob $job) => $job->shiftId === $overnight->id && $job->date === '2026-04-19');

        Carbon::setTestNow();
    }

    public function test_dispatches_end_job_for_morning_shift_at_14_00_vn(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-20 14:00:00', 'Asia/Ho_Chi_Minh'));

        $this->artisan('snapshot:capture-shift-boundaries')->assertSuccessful();

        $morning = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();
        $afternoon = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'CHIEU')
            ->firstOrFail();

        Queue::assertPushed(CaptureSlotEndJob::class, fn (CaptureSlotEndJob $job) => $job->shiftId === $morning->id && $job->date === '2026-04-20');
        Queue::assertPushed(CaptureSlotStartJob::class, fn (CaptureSlotStartJob $job) => $job->shiftId === $afternoon->id && $job->date === '2026-04-20');

        Carbon::setTestNow();
    }

    public function test_no_dispatch_when_no_shift_matches_current_hm(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-20 03:17:00', 'Asia/Ho_Chi_Minh'));

        $this->artisan('snapshot:capture-shift-boundaries')->assertSuccessful();

        Queue::assertNothingPushed();

        Carbon::setTestNow();
    }
}
