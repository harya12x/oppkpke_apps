@extends('layouts.oppkpke')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')
@section('page-subtitle', 'Jejak aktivitas sensitif sistem')

@section('content')
@php
    $badge = [
        'user.created' => 'bg-green-100 text-green-700', 'user.updated' => 'bg-blue-100 text-blue-700',
        'user.deleted' => 'bg-red-100 text-red-700', 'user.toggled' => 'bg-amber-100 text-amber-700',
        'user.password_reset' => 'bg-orange-100 text-orange-700',
        'announcement.created' => 'bg-green-100 text-green-700', 'announcement.updated' => 'bg-blue-100 text-blue-700',
        'announcement.toggled' => 'bg-amber-100 text-amber-700', 'announcement.deleted' => 'bg-red-100 text-red-700',
        'announcement.auto_expired' => 'bg-gray-100 text-gray-600',
        'chat.status_changed' => 'bg-cyan-100 text-cyan-700',
        'laporan.created' => 'bg-green-100 text-green-700', 'laporan.updated' => 'bg-blue-100 text-blue-700',
        'laporan.deleted' => 'bg-red-100 text-red-700',
        'pic.completed' => 'bg-indigo-100 text-indigo-700', 'pic.updated' => 'bg-indigo-100 text-indigo-700',
        'auth.login' => 'bg-emerald-100 text-emerald-700', 'auth.logout' => 'bg-gray-100 text-gray-600',
        'auth.login_failed' => 'bg-red-100 text-red-700',
    ];
@endphp
<div class="max-w-6xl mx-auto">

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.audit.index') }}"
          class="bg-white rounded-xl border shadow-sm p-3 md:p-4 mb-4 flex flex-wrap gap-2 md:gap-3 items-end">
        <div class="flex-1 min-w-[160px]">
            <label class="text-xs font-medium text-gray-600 block mb-1">Cari aktor / deskripsi / IP</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kata kunci..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Aksi</label>
            <select name="action" class="border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Aksi</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}" {{ ($filters['action'] ?? '') === $act ? 'selected' : '' }}>{{ $act }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Dari</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Sampai</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
        @if(array_filter($filters))
            <a href="{{ route('admin.audit.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">Reset</a>
        @endif
    </form>

    {{-- Tabel --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Waktu</th>
                        <th class="px-4 py-3 text-left">Aktor</th>
                        <th class="px-4 py-3 text-left">Aksi</th>
                        <th class="px-4 py-3 text-left">Deskripsi</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 text-xs">
                                {{ $log->created_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-medium text-gray-800">{{ $log->actor_name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $badge[$log->action] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $log->action_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $log->description }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-400 text-xs font-mono">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-gray-400">
                                <i class="fas fa-clipboard-list text-4xl mb-3 block"></i>
                                <p class="font-medium">Belum ada catatan audit</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
