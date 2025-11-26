<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Notification",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Payment Received"),
 *     @OA\Property(property="message", type="string", example="You received 10 SOL from merchant"),
 *     @OA\Property(property="type", type="string", example="transaction"),
 *     @OA\Property(property="read_at", type="string", format="date-time"),
 *     @OA\Property(property="action_url", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/notifications",
     *     tags={"Notifications"},
     *     summary="List notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="unread_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notifications retrieved")
     * )
     */
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id);

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($notifications);
    }

    /**
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Get unread count",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Count retrieved")
     * )
     */
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return $this->success(['count' => $count]);
    }

    /**
     * @OA\Get(
     *     path="/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Get notification",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification retrieved")
     * )
     */
    public function show(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->success($notification);
    }

    /**
     * @OA\Post(
     *     path="/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark as read",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Marked as read")
     * )
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return $this->success($notification, 'Notification marked as read');
    }

    /**
     * @OA\Post(
     *     path="/notifications/mark-all-read",
     *     tags={"Notifications"},
     *     summary="Mark all as read",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="All marked as read")
     * )
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read');
    }

    /**
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Delete notification",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification deleted")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return $this->success(null, 'Notification deleted');
    }

    /**
     * @OA\Delete(
     *     path="/notifications",
     *     tags={"Notifications"},
     *     summary="Clear all notifications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="All notifications cleared")
     * )
     */
    public function destroyAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->delete();

        return $this->success(null, 'All notifications cleared');
    }
}
