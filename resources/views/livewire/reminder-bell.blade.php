<?php

use App\Actions\SendDueTaskReminders;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

/*
 * The bell in the top bar. Every 30 seconds it sends any of this user's
 * reminders that are due, and pops up an alert for ones it hasn't shown yet.
 */
new class extends Component {
    /** created_at ("Y-m-d H:i:s") of the newest notification already shown on this page */
    public ?string $seenUpTo = null;

    public function mount(): void
    {
        $this->seenUpTo = auth()->user()->notifications()->latest()->first()?->created_at?->format('Y-m-d H:i:s');
    }

    public function check(SendDueTaskReminders $sender): void
    {
        $sender->handle(auth()->user());

        $new = auth()->user()->unreadNotifications()
            ->when($this->seenUpTo, fn ($query) => $query->where('created_at', '>', Carbon::parse($this->seenUpTo)))
            ->latest()
            ->limit(5)
            ->get();

        if ($new->isEmpty()) {
            return;
        }

        $this->seenUpTo = $new->first()->created_at->format('Y-m-d H:i:s');

        $this->dispatch('reminders-arrived', reminders: $new->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? 'Reminder',
            'body' => $n->data['body'] ?? '',
        ])->values()->all());
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function with(): array
    {
        $unreadCount = auth()->user()->unreadNotifications()->count();

        return [
            'unreadCount' => $unreadCount,
            'bellLabel' => $unreadCount ? "Reminders, {$unreadCount} unread" : 'Reminders',
            'recent' => auth()->user()->notifications()->latest()->limit(8)->get(),
        ];
    }
}; ?>

<div
    wire:poll.30s.keep-alive="check"
    x-data="{
        permission: ('Notification' in window) ? Notification.permission : 'unsupported',
        toasts: [],
        enableAlerts() {
            Notification.requestPermission().then((result) => this.permission = result);
        },
        ring(reminders) {
            this.beep();
            reminders.forEach((reminder) => {
                if (this.permission === 'granted') {
                    const n = new Notification(reminder.title, { body: reminder.body, tag: reminder.id });
                    n.onclick = () => { window.focus(); n.close(); };
                }
                this.toasts.push(reminder);
                setTimeout(() => this.dismiss(reminder.id), 20000);
            });
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
        beep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.3].forEach((offset) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.frequency.value = 880;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    gain.gain.setValueAtTime(0.2, ctx.currentTime + offset);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.25);
                    osc.start(ctx.currentTime + offset);
                    osc.stop(ctx.currentTime + offset + 0.25);
                });
            } catch (e) {}
        },
    }"
    x-on:reminders-arrived.window="ring($event.detail.reminders)"
>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" class="relative" :aria-label="$bellLabel">
            <flux:icon name="bell" class="size-5" />
            @if ($unreadCount)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-4 text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between px-2 py-1.5">
                <span class="text-sm font-semibold">Reminders</span>
                @if ($unreadCount)
                    <button type="button" wire:click="markAllRead" class="text-xs text-zinc-500 hover:text-zinc-800 hover:underline dark:text-zinc-400 dark:hover:text-white">Mark all read</button>
                @endif
            </div>

            <template x-if="permission === 'default'">
                <button type="button" x-on:click="enableAlerts()" class="mx-2 mb-1 flex w-[calc(100%-1rem)] items-center gap-2 rounded-md bg-violet-50 px-2 py-1.5 text-left text-xs text-violet-700 hover:bg-violet-100 dark:bg-violet-500/10 dark:text-violet-300">
                    <flux:icon name="computer-desktop" variant="micro" /> Turn on desktop alerts
                </button>
            </template>
            <template x-if="permission === 'denied'">
                <p class="mx-2 mb-1 text-xs text-zinc-500 dark:text-zinc-400">Desktop alerts are blocked for this site in your browser settings.</p>
            </template>

            <flux:menu.separator />

            @forelse ($recent as $notification)
                <flux:menu.item wire:click="open('{{ $notification->id }}')" wire:key="n-{{ $notification->id }}" class="!h-auto !items-start py-2">
                    <div class="flex min-w-0 flex-1 items-start gap-2">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-red-500' => ! $notification->read_at, 'bg-transparent' => $notification->read_at])></span>
                        <div class="min-w-0 flex-1">
                            <p @class(['truncate text-sm', 'font-semibold' => ! $notification->read_at])>{{ $notification->data['title'] ?? 'Reminder' }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </flux:menu.item>
            @empty
                <p class="px-2 py-3 text-sm text-zinc-500 dark:text-zinc-400">No reminders yet. Set one by editing a task.</p>
            @endforelse
        </flux:menu>
    </flux:dropdown>

    {{-- Pop-up alerts --}}
    <div wire:ignore class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="assertive">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto flex items-start gap-3 rounded-xl border border-violet-200 bg-white p-4 shadow-lg dark:border-violet-500/40 dark:bg-zinc-800" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5 shrink-0 text-violet-600 dark:text-violet-400" aria-hidden="true"><path fill-rule="evenodd" d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Zm4.502 8.9a2.25 2.25 0 1 0 4.496 0 25.057 25.057 0 0 1-4.496 0Z" clip-rule="evenodd" /></svg>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white" x-text="toast.title"></p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400" x-text="toast.body"></p>
                </div>
                <button type="button" x-on:click="dismiss(toast.id)" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-white" aria-label="Dismiss">&times;</button>
            </div>
        </template>
    </div>
</div>
