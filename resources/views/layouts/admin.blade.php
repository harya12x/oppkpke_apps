{{-- resources/views/layouts/admin.blade.php --}}

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - MNC University</title>
    
    {{-- Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>
    
    {{-- Font Awesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar { 
            width: 8px; 
            height: 8px; 
        }
        ::-webkit-scrollbar-track { 
            background: #f1f5f9; 
        }
        ::-webkit-scrollbar-thumb { 
            background: #cbd5e1; 
            border-radius: 4px; 
        }
        ::-webkit-scrollbar-thumb:hover { 
            background: #94a3b8; 
        }
        
        /* Smooth transitions */
        * {
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
    
    @yield('styles')
</head>
<body class="bg-slate-50 min-h-screen antialiased" x-data="{ sidebarOpen: true, mobileMenuOpen: false }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-slate-900 to-slate-800 transform transition-transform duration-300 lg:translate-x-0 shadow-xl"
               :class="{ '-translate-x-full': !sidebarOpen && !mobileMenuOpen, 'translate-x-0': sidebarOpen || mobileMenuOpen }">
            
            {{-- Logo --}}
            <div class="h-16 flex items-center justify-center border-b border-slate-700/50 bg-slate-900/50">
                <a href="{{ route('admin.kompetensi.prodi.index') }}" class="flex items-center gap-3 px-4 group">
                    <img src="{{ URL('Images/mncu_logo_new3.png') }}" alt="Logo" class="h-10 group-hover:scale-105 transition-transform duration-200">
                    <span class="text-white font-bold text-lg tracking-tight">Admin Panel</span>
                </a>
            </div>
            
            {{-- Navigation --}}
            <nav class="p-4 space-y-1 overflow-y-auto h-[calc(100vh-8rem)]">
                {{-- Dashboard --}}
                <a href="{{ route('admin.kompetensi.prodi.index') }}" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group {{ request()->routeIs('admin.kompetensi.prodi.index') ? 'bg-slate-700 text-white shadow-lg shadow-slate-900/50' : '' }}">
                    <i class="fas fa-home w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                
                {{-- Kompetensi Section --}}
                <div class="pt-6">
                    <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <span class="h-px flex-1 bg-slate-700"></span>
                        <span>Kompetensi</span>
                        <span class="h-px flex-1 bg-slate-700"></span>
                    </p>
                    
                    <a href="{{ route('admin.kompetensi.prodi.index') }}" 
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group {{ request()->routeIs('admin.kompetensi.prodi.*') ? 'bg-slate-700 text-white shadow-lg shadow-slate-900/50' : '' }}">
                        <i class="fas fa-graduation-cap w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Program Studi</span>
                    </a>
                    
                    @if(request()->route('kodeProdi'))
                    <a href="{{ route('admin.kompetensi.index', request()->route('kodeProdi')) }}" 
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group {{ request()->routeIs('admin.kompetensi.index') ? 'bg-slate-700 text-white shadow-lg shadow-slate-900/50' : '' }}">
                        <i class="fas fa-list w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Daftar Kompetensi</span>
                    </a>
                    
                    <a href="{{ route('admin.kompetensi.dashboard', request()->route('kodeProdi')) }}" 
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group {{ request()->routeIs('admin.kompetensi.dashboard') ? 'bg-slate-700 text-white shadow-lg shadow-slate-900/50' : '' }}">
                        <i class="fas fa-chart-bar w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Dashboard Prodi</span>
                    </a>
                    
                    <a href="{{ route('admin.kompetensi.logs', request()->route('kodeProdi')) }}" 
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group {{ request()->routeIs('admin.kompetensi.logs') ? 'bg-slate-700 text-white shadow-lg shadow-slate-900/50' : '' }}">
                        <i class="fas fa-history w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Log Generasi</span>
                    </a>
                    @endif
                </div>
                
                {{-- Mahasiswa Section --}}
                <div class="pt-6">
                    <p class="px-4 text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <span class="h-px flex-1 bg-slate-700"></span>
                        <span>Lainnya</span>
                        <span class="h-px flex-1 bg-slate-700"></span>
                    </p>
                    
                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group">
                        <i class="fas fa-users w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Mahasiswa</span>
                    </a>
                    
                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group">
                        <i class="fas fa-file-alt w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Laporan</span>
                    </a>
                    
                    <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 group">
                        <i class="fas fa-cog w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span class="font-medium">Pengaturan</span>
                    </a>
                </div>
            </nav>
            
            {{-- User Info at Bottom --}}
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700/50 bg-slate-900/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-semibold truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <p class="text-slate-400 text-xs truncate">{{ auth()->user()->email ?? 'admin@mncu.ac.id' }}</p>
                    </div>
                    {{-- <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-white p-2 hover:bg-slate-700/50 rounded-lg transition-colors" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form> --}}
                </div>
            </div>
        </aside>
        
        {{-- Main Content --}}
        <div class="flex-1 lg:ml-64">
            {{-- Top Navbar --}}
            <header class="sticky top-0 z-40 h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 shadow-sm">
                {{-- Mobile Menu Toggle --}}
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                {{-- Sidebar Toggle (Desktop) --}}
                <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:block p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                {{-- Page Title --}}
                <div class="flex-1 px-4">
                    <h1 class="text-lg font-bold text-slate-800 tracking-tight">@yield('title', 'Dashboard')</h1>
                </div>
                
                {{-- Right Side --}}
                <div class="flex items-center gap-2">
                    {{-- Notifications --}}
                    <button class="relative p-2.5 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors group">
                        <i class="fas fa-bell text-lg group-hover:scale-110 transition-transform"></i>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
                    </button>
                    
                    {{-- Quick Actions --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 px-4 py-2.5 text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors font-medium">
                            <i class="fas fa-plus"></i>
                            <span class="hidden sm:inline">Quick Add</span>
                            <i class="fas fa-chevron-down text-xs ml-1" :class="{ 'rotate-180': open }"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-2 overflow-hidden">
                            <a href="{{ route('admin.kompetensi.prodi.create') }}" class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-colors group">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-graduation-cap text-blue-600"></i>
                                </div>
                                <span class="font-medium">Tambah Prodi</span>
                            </a>
                            @if(request()->route('kodeProdi'))
                            <a href="{{ route('admin.kompetensi.create', request()->route('kodeProdi')) }}" class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-colors group">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                    <i class="fas fa-plus-circle text-green-600"></i>
                                </div>
                                <span class="font-medium">Tambah Kompetensi</span>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </header>
            
            {{-- Page Content --}}
            <main class="p-6">
                @yield('content')
            </main>
            
            {{-- Footer --}}
            <footer class="border-t border-slate-200 p-4 text-center text-sm text-slate-500 bg-white">
                <p class="font-medium">&copy; {{ date('Y') }} MNC University. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    {{-- Mobile Overlay --}}
    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 lg:hidden">
    </div>
    
    {{-- Toast Notifications --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-50 bg-green-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 max-w-md">
        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
            <i class="fas fa-check-circle text-xl"></i>
        </div>
        <span class="flex-1 font-medium">{{ session('success') }}</span>
        <button @click="show = false" class="p-1 hover:bg-white/10 rounded-lg transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif
    
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-6 right-6 z-50 bg-red-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 max-w-md">
        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
            <i class="fas fa-exclamation-circle text-xl"></i>
        </div>
        <span class="flex-1 font-medium">{{ session('error') }}</span>
        <button @click="show = false" class="p-1 hover:bg-white/10 rounded-lg transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif
    
    @yield('scripts')
</body>
</html>