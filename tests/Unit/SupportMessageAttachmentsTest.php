<?php

namespace Tests\Unit;

use App\Http\Controllers\Support\Messages\SendingController;
use App\Mail\SupportMessageSent;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class SupportMessageAttachmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['ninja.contact.ninja_official_contact' => 'support@example.com']);
        Route::post('/test-support-message', SendingController::class);
    }

    public function test_support_message_can_be_sent_without_attachments(): void
    {
        $this->postJson('/test-support-message', ['message' => 'Please help.'])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertSent(SupportMessageSent::class);
    }

    public function test_images_totalling_exactly_ten_megabytes_are_passed_to_the_mailable(): void
    {
        $attachments = [
            UploadedFile::fake()->image('first.png')->size(5120),
            UploadedFile::fake()->image('second.jpg')->size(5120),
        ];

        $this->postJson('/test-support-message', [
            'message' => 'Please help.',
            'attachments' => $attachments,
        ])->assertOk();

        Mail::assertSent(SupportMessageSent::class, function ($mail) use ($attachments) {
            return $mail->data['attachments'] === $attachments;
        });
    }

    public function test_combined_size_one_byte_over_ten_megabytes_is_rejected(): void
    {
        $first = UploadedFile::fake()->image('first.png')->size(5120);
        $second = UploadedFile::fake()->image('second.png')->size(5120);
        $second->sizeToReport++;

        $this->postJson('/test-support-message', [
            'message' => 'Please help.',
            'attachments' => [$first, $second],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments');

        Mail::assertNothingSent();
    }

    public function test_non_image_disguised_as_png_is_rejected(): void
    {
        $text = UploadedFile::fake()->createWithContent('notes.txt', 'This is not an image.');
        $attachment = new UploadedFile($text->getPathname(), 'screenshot.png', 'image/png', null, true);

        $this->postJson('/test-support-message', [
            'message' => 'Please help.',
            'attachments' => [$attachment],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

        Mail::assertNothingSent();
    }

    public function test_attachments_must_be_an_array_of_files(): void
    {
        $this->postJson('/test-support-message', [
            'message' => 'Please help.',
            'attachments' => 'screenshot.png',
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $this->postJson('/test-support-message', [
            'message' => 'Please help.',
            'attachments' => [['nested' => 'screenshot.png']],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

        Mail::assertNothingSent();
    }

    public function test_mailable_attaches_images_with_original_names_and_detected_mime_types(): void
    {
        config(['ninja.production' => false]);

        $company = Mockery::mock(Company::class)->makePartial();
        $company->db = 'db-ninja-01';
        $company->company_key = 'Company123';
        $company->shouldReceive('present->logo')->andReturn('');

        $account = new Account();
        $account->plan = 'pro';

        $user = Mockery::mock(User::class)->makePartial();
        $user->email = 'sender@example.com';
        $user->first_name = 'Support';
        $user->last_name = 'Sender';
        $user->setRelation('account', $account);
        $user->shouldReceive('company')->andReturn($company);
        $this->be($user);

        $image = UploadedFile::fake()->image('screenshot.png');
        $mail = new SupportMessageSent([
            'message' => 'Please help.',
            'attachments' => [$image],
        ], false);
        $mail->build();

        $this->assertTrue($mail->hasAttachment($image->getRealPath(), [
            'as' => 'screenshot.png',
            'mime' => 'image/png',
        ]));
    }
}
