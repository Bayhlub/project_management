@php
    $user = auth()->user();

    // Overdue + due today, shown next to "To-do".
    $dueNow = $user->tasks()->open()->whereNotNull('due_date')->whereDate('due_date', '<=', today())->count();

    $sidebarProjects = $user->projects()
        ->notArchived()
        ->where('status', '!=', \App\Enums\ProjectStatus::Done->value)
        ->withCount(['tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at')])
        ->orderBy('name')
        ->limit(12)
        ->get();

    $trashCount = \App\Support\Trash::count();

    $routeProject = request()->routeIs('projects.show') ? request()->route('project') : null;
    $currentProjectId = $routeProject instanceof \App\Models\Project ? $routeProject->id : (int) $routeProject;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-1 py-1" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline" class="mt-2">
                <flux:navlist.item icon="check-circle" :href="route('dashboard')" :current="request()->routeIs('dashboard')" :badge="$dueNow ?: null" wire:navigate>To-do</flux:navlist.item>
                <flux:navlist.item icon="folder" :href="route('projects.index')" :current="request()->routeIs('projects.index')" wire:navigate>Projects</flux:navlist.item>
            </flux:navlist>

            @if ($sidebarProjects->isNotEmpty())
                <div class="mt-4">
                    <h2 class="px-3 pb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">Current projects</h2>
                    <ul class="flex flex-col gap-px">
                        @foreach ($sidebarProjects as $p)
                            @php($isCurrent = $currentProjectId === $p->id)
                            <li>
                                <a
                                    href="{{ route('projects.show', $p) }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm transition-colors',
                                        'bg-zinc-100 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' => $isCurrent,
                                        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => ! $isCurrent,
                                    ])
                                    @if ($isCurrent) aria-current="page" @endif
                                >
                                    <span class="size-2 shrink-0 rounded-full {{ $p->color->dotClass() }}" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $p->name }}</span>
                                    @if ($p->open_tasks_count)
                                        <span class="text-xs tabular-nums text-zinc-400">{{ $p->open_tasks_count }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="trash" :href="route('trash')" :current="request()->routeIs('trash')" :badge="$trashCount ?: null" wire:navigate>Trash</flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="$user->name"
                    :initials="$user->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-evergreen-100 text-evergreen-700 dark:bg-evergreen-900 dark:text-evergreen-300">
                                        {{ $user->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ $user->name }}</span>
                                    <span class="truncate text-xs">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Log out
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-950">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ml-2 flex items-center gap-2" wire:navigate>
                <x-app-logo-icon class="size-7" />
                <span class="text-sm font-semibold">{{ config('app.name') }}</span>
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$user->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ $user->name }}</span>
                                    <span class="truncate text-xs">{{ $user->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Log out
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
