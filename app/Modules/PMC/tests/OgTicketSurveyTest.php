<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OgTicketSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function baseUrl(int $ogTicketId): string
    {
        return "/api/v1/pmc/og-tickets/{$ogTicketId}/survey";
    }

    public function test_show_creates_empty_survey_when_missing(): void
    {
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $response = $this->getJson($this->baseUrl($ogTicket->id));

        $response->assertSuccessful()
            ->assertJsonPath('data.og_ticket_id', $ogTicket->id)
            ->assertJsonPath('data.note', null)
            ->assertJsonPath('data.attachments', []);

        $this->assertDatabaseHas('og_ticket_surveys', ['og_ticket_id' => $ogTicket->id]);
    }

    public function test_show_returns_existing_survey(): void
    {
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();
        OgTicketSurvey::query()->create([
            'og_ticket_id' => $ogTicket->id,
            'note' => 'Hiện trạng cũ',
        ]);

        $response = $this->getJson($this->baseUrl($ogTicket->id));

        $response->assertSuccessful()
            ->assertJsonPath('data.note', 'Hiện trạng cũ');
    }

    public function test_upsert_saves_note_and_uploads_attachments(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $response = $this->postJson($this->baseUrl($ogTicket->id), [
            'note' => 'Tường nứt, sàn loang',
            'attachments' => [
                UploadedFile::fake()->image('photo.jpg'),
                UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
            ],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.note', 'Tường nứt, sàn loang')
            ->assertJsonCount(2, 'data.attachments');

        $this->assertDatabaseHas('og_ticket_surveys', [
            'og_ticket_id' => $ogTicket->id,
            'note' => 'Tường nứt, sàn loang',
        ]);
        $this->assertDatabaseCount('attachments', 2);
    }

    public function test_upsert_appends_attachments_keeping_existing(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $this->postJson($this->baseUrl($ogTicket->id), [
            'note' => 'lần 1',
            'attachments' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSuccessful();

        $response = $this->postJson($this->baseUrl($ogTicket->id), [
            'note' => 'lần 2',
            'attachments' => [UploadedFile::fake()->image('b.jpg')],
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.note', 'lần 2')
            ->assertJsonCount(2, 'data.attachments');
    }

    public function test_upsert_rejects_oversized_file(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $response = $this->postJson($this->baseUrl($ogTicket->id), [
            'attachments' => [UploadedFile::fake()->create('big.mp4', 101 * 1024, 'video/mp4')],
        ]);

        $response->assertUnprocessable();
    }

    public function test_upsert_rejects_too_many_files(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = UploadedFile::fake()->image("p{$i}.jpg");
        }

        $response = $this->postJson($this->baseUrl($ogTicket->id), [
            'attachments' => $files,
        ]);

        $response->assertUnprocessable();
    }

    public function test_upsert_rejects_disallowed_mime(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $response = $this->postJson($this->baseUrl($ogTicket->id), [
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ]);

        $response->assertUnprocessable();
    }

    public function test_delete_attachment_removes_record_and_file(): void
    {
        Storage::fake();
        $this->actingAsAdmin();
        $ogTicket = OgTicket::factory()->create();

        $upload = $this->postJson($this->baseUrl($ogTicket->id), [
            'attachments' => [UploadedFile::fake()->image('x.jpg')],
        ])->assertSuccessful();

        $attachmentId = $upload->json('data.attachments.0.id');

        $response = $this->deleteJson($this->baseUrl($ogTicket->id)."/attachments/{$attachmentId}");

        $response->assertSuccessful()
            ->assertJsonCount(0, 'data.attachments');
    }

    public function test_show_returns_404_when_og_ticket_missing(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson($this->baseUrl(999999));

        $response->assertNotFound();
    }
}
