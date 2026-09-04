<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService
{
    public function record(Request $request, string $event, ?object $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::create(['user_id' => $request->user()?->id, 'event' => $event, 'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->id, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $metadata]);
    }
}
