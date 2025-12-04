<?php

namespace App\Services\Notifications\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Template Controller
 *
 * Handles CRUD operations for notification templates.
 * Admin-only access.
 */
class TemplateController extends Controller
{
    /**
     * List all notification templates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $query = NotificationTemplate::with('creator')
            ->orderBy('created_at', 'desc');

        // Filter by event_type
        if ($request->filled('event_type')) {
            $query->byEventType($request->input('event_type'));
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->byChannel($request->input('channel'));
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            if ($isActive) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        $templates = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $templates,
        ], 200);
    }

    /**
     * Show a specific template.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $template = NotificationTemplate::with('creator')->find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ], 200);
    }

    /**
     * Create a new notification template.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:notification_templates,name'],
            'event_type' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms,telegram,push'],
            'subject_template' => ['nullable', 'string'],
            'body_template' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Validate template syntax
        $validationErrors = $this->validateTemplateSyntax($data);
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Template syntax validation failed.',
                'errors' => $validationErrors,
            ], 422);
        }

        $data['created_by'] = Auth::guard('admin')->id();

        $template = NotificationTemplate::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'data' => $template,
        ], 201);
    }

    /**
     * Update an existing template.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = NotificationTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:notification_templates,name,' . $id],
            'event_type' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'in:email,sms,telegram,push'],
            'subject_template' => ['nullable', 'string'],
            'body_template' => ['sometimes', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Validate template syntax if templates are being updated
        if (isset($data['subject_template']) || isset($data['body_template'])) {
            $validationData = array_merge([
                'subject_template' => $template->subject_template,
                'body_template' => $template->body_template,
            ], $data);

            $validationErrors = $this->validateTemplateSyntax($validationData);
            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template syntax validation failed.',
                    'errors' => $validationErrors,
                ], 422);
            }
        }

        $template->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'data' => $template->fresh(),
        ], 200);
    }

    /**
     * Delete a template.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $template = NotificationTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.',
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ], 200);
    }

    /**
     * Validate template syntax.
     *
     * Checks for balanced {{ }} placeholders and proper formatting.
     *
     * @param array $data
     * @return array
     */
    protected function validateTemplateSyntax(array $data): array
    {
        $errors = [];

        // Validate subject_template if present
        if (!empty($data['subject_template'])) {
            $subjectErrors = $this->checkTemplatePlaceholders($data['subject_template']);
            if (!empty($subjectErrors)) {
                $errors['subject_template'] = $subjectErrors;
            }
        }

        // Validate body_template
        if (!empty($data['body_template'])) {
            $bodyErrors = $this->checkTemplatePlaceholders($data['body_template']);
            if (!empty($bodyErrors)) {
                $errors['body_template'] = $bodyErrors;
            }
        }

        return $errors;
    }

    /**
     * Check template for valid placeholder syntax.
     *
     * @param string $template
     * @return array
     */
    protected function checkTemplatePlaceholders(string $template): array
    {
        $errors = [];

        // Count opening and closing braces
        $openCount = substr_count($template, '{{');
        $closeCount = substr_count($template, '}}');

        if ($openCount !== $closeCount) {
            $errors[] = 'Unbalanced template placeholders: ' . $openCount . ' opening {{ vs ' . $closeCount . ' closing }}.';
        }

        // Check for malformed placeholders (single braces)
        if (preg_match('/(?<!\{)\{(?!\{)/', $template) || preg_match('/(?<!\})\}(?!\})/', $template)) {
            $errors[] = 'Found malformed placeholders. Use {{ }} for variables, not single braces.';
        }

        // Check for empty placeholders
        if (preg_match('/\{\{\s*\}\}/', $template)) {
            $errors[] = 'Empty placeholders found. Please provide variable names between {{ }}.';
        }

        return $errors;
    }
}
