<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    private const ENTITY_MAP = [
        'MenuItem'   => 'App\\Models\\MenuItem',
        'User'       => 'App\\Models\\User',
        'Ingredient' => 'App\\Models\\Ingredient',
        'Area'       => 'App\\Models\\Area',
        'Table'      => 'App\\Models\\Table',
    ];

    public function index(Request $request): Response
    {
        $request->validate([
            'entity'  => 'nullable|in:MenuItem,User,Ingredient,Area,Table',
            'action'  => 'nullable|in:created,updated,deleted',
            'user_id' => 'nullable|integer|exists:users,id',
            'from'    => 'nullable|date',
            'to'      => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from')
            ? now()->parse($request->from)->startOfDay()
            : now()->subDays(6)->startOfDay();

        $to = $request->filled('to')
            ? now()->parse($request->to)->endOfDay()
            : now()->endOfDay();

        $logs = AuditLog::with('user:id,name')
            ->when($request->filled('entity'), fn ($q) => $q->where('auditable_type', self::ENTITY_MAP[$request->entity]))
            ->when($request->filled('action'),  fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString()
            ->through(fn ($l) => [
                'id'              => $l->id,
                'user'            => $l->user?->name ?? 'Sistema',
                'action'          => $l->action,
                'entity'          => $l->entityName(),
                'auditable_label' => $l->auditable_label,
                'old_values'      => $l->old_values,
                'new_values'      => $l->new_values,
                'created_at'      => $l->created_at->toIso8601String(),
            ]);

        $users = User::where('active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs'    => $logs,
            'users'   => $users,
            'entities' => array_keys(self::ENTITY_MAP),
            'filters' => [
                'entity'  => $request->entity,
                'action'  => $request->action,
                'user_id' => $request->user_id,
                'from'    => $from->toDateString(),
                'to'      => $to->toDateString(),
            ],
        ]);
    }
}
