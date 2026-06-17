<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NewReportNotification;
use App\Notifications\ReportStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /* ════════════════════════════════════════════════════════════════
     |  POST /reports — user files a report (message or profile)
     |  Payload: { type, reason, reportedUserId, conversationId?, messageId?, details? }
     * ════════════════════════════════════════════════════════════════ */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'           => 'required|in:message,profile',
            'reason'         => 'required|in:harassment,spam,sexual_content,hate_speech,scam_fraud,threats_violence,other',
            'reportedUserId' => 'required|integer|exists:users,id',
            'conversationId' => 'nullable|integer|exists:conversations,id',
            'messageId'      => 'required_if:type,message|nullable|integer|exists:messages,id',
            'details'        => 'required_if:reason,other|nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $me             = $request->user();
        $reportedUserId = (int) $request->input('reportedUserId');

        if ($reportedUserId === (int) $me->id) {
            return ApiResponse::error('You cannot report yourself.', [], 422);
        }

        $type           = $request->input('type');
        $conversationId = $request->input('conversationId');
        $messageId      = $request->input('messageId');

        // If a conversation is referenced, the reporter must be part of it —
        // stops anyone snapshotting a thread they're not in.
        if ($conversationId) {
            $conversation = Conversation::find($conversationId);
            if (!$conversation || !$conversation->hasParticipant($me->id)) {
                return ApiResponse::error('You are not part of this conversation.', [], 403);
            }
        }

        // Evidence snapshots — captured now so the report survives even if the
        // message/conversation is later deleted by either party.
        $reportedMessage = null;
        if ($messageId) {
            $m = Message::find($messageId);
            $reportedMessage = $m ? [
                'id'             => $m->id,
                'conversationId' => (int) $m->conversationId,
                'senderId'       => (int) $m->senderId,
                'body'           => $m->body,
                'createdAt'      => $m->created_at?->toISOString(),
            ] : null;
        }

        $contextSnapshot = [];
        if ($conversationId) {
            $contextSnapshot = Message::where('conversationId', $conversationId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->reverse()
                ->values()
                ->map(fn ($m) => [
                    'id'        => $m->id,
                    'senderId'  => (int) $m->senderId,
                    'body'      => $m->body,
                    'createdAt' => $m->created_at?->toISOString(),
                ])
                ->all();
        }

        $report = Report::create([
            'reporterId'      => $me->id,
            'reportedUserId'  => $reportedUserId,
            'conversationId'  => $conversationId,
            'messageId'       => $messageId,
            'type'            => $type,
            'reason'          => $request->input('reason'),
            'details'         => $request->input('details'),
            'reportedMessage' => $reportedMessage,
            'contextSnapshot' => $contextSnapshot,
            'status'          => 'pending',
        ]);

         try {
            $admins = User::where('role', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewReportNotification($report));
                 Log::success('Report: admin notification sent', ['reportId' => $report->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('Report: admin notification failed', [
                'reportId' => $report->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return ApiResponse::success('Report submitted. Our team will review it shortly.', [
            'report' => [
                'id'     => $report->id,
                'status' => $report->status,
            ],
        ], 201);
    }

    /* ════════════════════════════════════════════════════════════════
     |  ADMIN — endpoints ready; dashboard UI is the next phase.
     * ════════════════════════════════════════════════════════════════ */

    // GET /admin/reports?status=&reason=
    public function index(Request $request)
    {
        $reports = Report::with(['reporter', 'reportedUser'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('reason'), fn ($q, $r) => $q->where('reason', $r))
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success('Reports loaded', [
            'reports' => $reports->items(),
            'meta'    => [
                'currentPage' => $reports->currentPage(),
                'lastPage'    => $reports->lastPage(),
                'total'       => $reports->total(),
                'perPage'     => $reports->perPage(),
            ],
        ]);
    }

    // GET /admin/reports/{id}
    public function show($id)
    {
        $report = Report::with(['reporter', 'reportedUser'])->find($id);
        if (!$report) {
            return ApiResponse::error('Report not found', [], 404);
        }
        return ApiResponse::success('Report loaded', ['report' => $report]);
    }

    // PATCH /admin/reports/{id}/status  { status }
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewed,resolved,dismissed',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $report = Report::find($id);
        if (!$report) {
            return ApiResponse::error('Report not found', [], 404);
        }

        $newStatus = $request->input('status');
        $oldStatus = $report->status;

        $report->update(['status' => $newStatus]);

        if ($newStatus !== $oldStatus) {
            try {
                $reporter = User::find($report->reporterId);
                if ($reporter) {
                    $reporter->notify(new ReportStatusUpdatedNotification($report));
                    Log::success('Report: reporter notification sent', ['reportId' => $report->id]);
                }
            } catch (\Throwable $e) {
                Log::warning('Report: reporter notification failed', [
                    'reportId' => $report->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
        return ApiResponse::success('Report status updated', ['report' => $report]);
    }
}