<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Penampil audit trail (SEC4) — hanya Admin Master (role:master di route).
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('actor_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%");
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(30)->withQueryString();

        // Daftar action unik untuk filter dropdown.
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', [
            'logs'     => $logs,
            'actions'  => $actions,
            'filters'  => $request->only(['action', 'q', 'from', 'to']),
        ]);
    }
}
