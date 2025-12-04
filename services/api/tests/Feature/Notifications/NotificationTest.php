<?php

namespace Tests\Feature\Notifications;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationJob;
use App\Mail\GenericNotificationMail;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notification Service Test
 *
 * Tests the notification service including event creation,
 * template rendering, job dispatching, and multi-channel sending.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that creating a notification requires API key.
     *
     * @return void
     */
    public function test_create_notification_requires_api_key(): void
    {
        config(['notifications.api_key' => 'test-api-key']);

        $response = $this->postJson('/api/v1/notifications/events', [
            'event_type' => 'user.registered',
            'channel' => 'email',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. Invalid or missing API key.',
        ]);

        // Test with valid API key
        $response = $this->withHeaders([
            'X-API-KEY' => 'test-api-key',
        ])->postJson('/api/v1/notifications/events', [
            'event_type' => 'user.registered',
            'channel' => 'email',
        ]);

        // Will fail for other reasons (no template), but should pass API key check
        $response->assertStatus(404); // No template found
    }

    /**
     * Test that create event creates notification and dispatches job.
     *
     * @return void
     */
    public function test_create_event_creates_notification_and_dispatches_job(): void
    {
        Event::fake([NotificationCreated::class]);
        Bus::fake([SendNotificationJob::class]);

        config(['notifications.api_key' => 'test-api-key']);

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create a template
        $template = NotificationTemplate::create([
            'name' => 'User Registered',
            'event_type' => 'user.registered',
            'channel' => 'email',
            'subject_template' => 'Welcome {{ name }}!',
            'body_template' => 'Hello {{ name }}, welcome to {{ app_name }}!',
            'is_active' => true,
        ]);

        // Create notification via API
        $response = $this->withHeaders([
            'X-API-KEY' => 'test-api-key',
        ])->postJson('/api/v1/notifications/events', [
            'user_id' => $user->id,
            'event_type' => 'user.registered',
            'channel' => 'email',
            'payload' => [
                'name' => 'John Doe',
                'app_name' => 'MyXen',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Notification created and queued for sending.',
        ]);

        // Assert notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'event_type' => 'user.registered',
            'channel' => 'email',
            'to' => 'test@example.com',
            'subject' => 'Welcome John Doe!',
            'body' => 'Hello John Doe, welcome to MyXen!',
            'status' => Notification::STATUS_PENDING,
        ]);

        // Assert event was fired
        Event::assertDispatched(NotificationCreated::class, function ($event) use ($user) {
            return $event->notification->user_id === $user->id
                && $event->notification->event_type === 'user.registered';
        });
    }

    /**
     * Test that SendNotificationJob sends email.
     *
     * @return void
     */
    public function test_send_notification_job_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'event_type' => 'user.registered',
            'channel' => Notification::CHANNEL_EMAIL,
            'to' => 'test@example.com',
            'subject' => 'Welcome',
            'body' => 'Hello, welcome!',
            'status' => Notification::STATUS_PENDING,
            'attempts' => 0,
        ]);

        // Dispatch the job
        $job = new SendNotificationJob($notification);
        $job->handle();

        // Assert email was sent
        Mail::assertSent(GenericNotificationMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });

        // Assert notification status updated
        $notification->refresh();
        $this->assertEquals(Notification::STATUS_SENT, $notification->status);
        $this->assertNotNull($notification->sent_at);
    }

    /**
     * Test that resend queues notification and increments attempts.
     *
     * @return void
     */
    public function test_resend_queues_notification_and_increments_attempts(): void
    {
        Bus::fake([SendNotificationJob::class]);

        // Create admin
        $admin = Admin::factory()->create([
            'email' => 'admin@myxen.com',
            'role' => 'superadmin',
        ]);

        $user = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'event_type' => 'test.event',
            'channel' => Notification::CHANNEL_EMAIL,
            'to' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Test body',
            'status' => Notification::STATUS_FAILED,
            'attempts' => 2,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson("/api/v1/admin/notifications/{$notification->id}/resend")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification queued for resending.',
            ]);

        // Assert job was dispatched
        Bus::assertDispatched(SendNotificationJob::class, function ($job) use ($notification) {
            return $job->notification->id === $notification->id;
        });

        // Assert attempts incremented
        $notification->refresh();
        $this->assertEquals(3, $notification->attempts);
        $this->assertEquals(Notification::STATUS_PENDING, $notification->status);
    }

    /**
     * Test admin can list notifications with filters.
     *
     * @return void
     */
    public function test_admin_can_list_notifications_with_filters(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@myxen.com',
            'role' => 'superadmin',
        ]);

        $user = User::factory()->create();

        // Create notifications with different attributes
        Notification::create([
            'user_id' => $user->id,
            'event_type' => 'user.registered',
            'channel' => 'email',
            'to' => 'test1@example.com',
            'subject' => 'Welcome',
            'body' => 'Hello',
            'status' => Notification::STATUS_SENT,
            'attempts' => 1,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'event_type' => 'payment.completed',
            'channel' => 'sms',
            'to' => '+1234567890',
            'body' => 'Payment received',
            'status' => Notification::STATUS_FAILED,
            'attempts' => 3,
        ]);

        // Test listing all notifications
        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/notifications')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total'));

        // Test filtering by event_type
        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/notifications?event_type=user.registered')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('user.registered', $response->json('data.data.0.event_type'));

        // Test filtering by status
        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/notifications?status=failed')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('failed', $response->json('data.data.0.status'));

        // Test filtering by channel
        $response = $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/notifications?channel=sms')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals('sms', $response->json('data.data.0.channel'));
    }

    /**
     * Test admin can manage templates.
     *
     * @return void
     */
    public function test_admin_can_manage_templates(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@myxen.com',
            'role' => 'superadmin',
        ]);

        // Create template
        $response = $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/templates', [
                'name' => 'User Registered',
                'event_type' => 'user.registered',
                'channel' => 'email',
                'subject_template' => 'Welcome {{ name }}',
                'body_template' => 'Hello {{ name }}, welcome!',
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Template created successfully.',
            ]);        $templateId = $response->json('data.id');

        // List templates
        $this->actingAs($admin, 'admin')
            ->getJson('/api/v1/admin/templates')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                ],
            ]);

        // Show template
        $this->actingAs($admin, 'admin')
            ->getJson("/api/v1/admin/templates/{$templateId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'User Registered',
                ],
            ]);

        // Update template
        $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/templates/{$templateId}", [
                'body_template' => 'Updated body with {{ name }}',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Template updated successfully.',
            ]);

        // Delete template
        $this->actingAs($admin, 'admin')
            ->deleteJson("/api/v1/admin/templates/{$templateId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Template deleted successfully.',
            ]);

        $this->assertDatabaseMissing('notification_templates', [
            'id' => $templateId,
        ]);
    }

    /**
     * Test template syntax validation.
     *
     * @return void
     */
    public function test_template_syntax_validation(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@myxen.com',
            'role' => 'superadmin',
        ]);

        // Test unbalanced braces
        $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/templates', [
                'name' => 'Invalid Template',
                'event_type' => 'test.event',
                'channel' => 'email',
                'body_template' => 'Hello {{ name',
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['body_template'],
            ]);

        // Test empty placeholders
        $this->actingAs($admin, 'admin')
            ->postJson('/api/v1/admin/templates', [
                'name' => 'Invalid Template 2',
                'event_type' => 'test.event',
                'channel' => 'email',
                'body_template' => 'Hello {{  }}',
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['body_template'],
            ]);
    }
}
