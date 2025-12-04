<?php

namespace App\Services\Notifications\Controllers;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Notification Controller
 *
 * Handles notification management operations for administrators
 * and event-driven notification creation for internal services.
 */
class NotificationController extends Controller
{
    /**
     * List all notifications with pagination and filters.
     *
     * Admin endpoint for viewing notification history.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $query = Notification::with(['user', 'creator'])
            ->orderBy('created_at', 'desc');

        // Filter by event_type
        if ($request->filled('event_type')) {
            $query->byEventType($request->input('event_type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->byChannel($request->input('channel'));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ], 200);
    }

    /**
     * Show a specific notification.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $notification = Notification::with(['user', 'creator'])->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $notification,
        ], 200);
    }

    /**
     * Resend a notification.
     *
     * Resets the notification status to pending and dispatches
     * the SendNotificationJob again.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function resend(int $id): JsonResponse
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        // Reset status and increment attempts
        $notification->status = Notification::STATUS_PENDING;
        $notification->attempts++;
        $notification->save();

        // Dispatch the job
        SendNotificationJob::dispatch($notification);

        Log::info('Notification resend initiated', [
            'notification_id' => $notification->id,
            'attempts' => $notification->attempts,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification queued for resending.',
            'data' => [
                'notification_id' => $notification->id,
                'status' => $notification->status,
                'attempts' => $notification->attempts,
            ],
        ], 200);
    }

    /**
     * Create a notification event.
     *
     * Public/internal endpoint for creating notifications based on events.
     * Requires API key authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'event_type' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms,telegram,push'],
            'to' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Find active template for this event type and channel
        $template = NotificationTemplate::findActiveTemplate(
            $data['event_type'],
            $data['channel']
        );

        if (!$template) {
            Log::warning('No active template found for notification', [
                'event_type' => $data['event_type'],
                'channel' => $data['channel'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No active template found for this event type and channel.',
            ], 404);
        }

        // Render template with payload variables
        $subject = $template->renderSubject($data['payload'] ?? []);
        $body = $template->renderBody($data['payload'] ?? []);

        // Determine recipient
        $to = $data['to'] ?? null;
        if (!$to && isset($data['user_id'])) {
            $user = \App\Models\User::find($data['user_id']);
            if ($user) {
                $to = $data['channel'] === 'email' ? $user->email : $user->phone ?? $user->email;
            }
        }

        if (!$to) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient address (to) is required.',
            ], 422);
        }

        // Create notification
        $notification = Notification::create([
            'user_id' => $data['user_id'] ?? null,
            'event_type' => $data['event_type'],
            'channel' => $data['channel'],
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'payload' => $data['payload'] ?? null,
            'status' => Notification::STATUS_PENDING,
            'attempts' => 0,
            'created_by' => auth()->id(),
        ]);

        // Fire event (which triggers the listener to dispatch SendNotificationJob)
        event(new NotificationCreated($notification));

        Log::info('Notification event created', [
            'notification_id' => $notification->id,
            'event_type' => $data['event_type'],
            'channel' => $data['channel'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification created and queued for sending.',
            'data' => [
                'notification_id' => $notification->id,
                'event_type' => $notification->event_type,
                'channel' => $notification->channel,
                'status' => $notification->status,
            ],
        ], 201);
    }
}
