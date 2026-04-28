<?php

use App\Models\UserNotificationSetting;
use Livewire\Component;

new class extends Component
{
    public bool $wishlist_joined = true;
    public bool $item_claimed = true;
    public bool $item_unclaimed = true;
    public bool $wishlist_updated = true;
    public bool $event_reminders = true;
    public bool $marketing = false;

    public array $reminder_days = [7, 3, 1];

    public function mount(): void
    {
        $settings = UserNotificationSetting::query()->firstOrCreate(
            ['user_id' => auth()->id()],
            [
                'wishlist_joined' => true,
                'item_claimed' => true,
                'item_unclaimed' => true,
                'wishlist_updated' => true,
                'event_reminders' => true,
                'marketing' => false,
                'reminder_days' => [7, 3, 1],
            ]
        );

        $this->wishlist_joined = $settings->wishlist_joined;
        $this->item_claimed = $settings->item_claimed;
        $this->item_unclaimed = $settings->item_unclaimed;
        $this->wishlist_updated = $settings->wishlist_updated;
        $this->event_reminders = $settings->event_reminders;
        $this->marketing = $settings->marketing;
        $this->reminder_days = $settings->reminder_days ?: [7, 3, 1];
    }

    public function toggleReminderDay(int $day): void
    {
        if (! in_array($day, [1, 3, 7, 14, 30], true)) {
            return;
        }

        if (in_array($day, $this->reminder_days, true)) {
            $this->reminder_days = array_values(array_filter(
                $this->reminder_days,
                fn ($item) => (int) $item !== $day
            ));

            return;
        }

        $this->reminder_days[] = $day;
        sort($this->reminder_days);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'wishlist_joined' => ['boolean'],
            'item_claimed' => ['boolean'],
            'item_unclaimed' => ['boolean'],
            'wishlist_updated' => ['boolean'],
            'event_reminders' => ['boolean'],
            'marketing' => ['boolean'],
            'reminder_days' => ['array'],
            'reminder_days.*' => ['integer', 'in:1,3,7,14,30'],
        ]);

        UserNotificationSetting::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        $this->dispatch('telegram-haptic-success');
    }
};
?>

<div class="min-h-screen pb-[110px]">
    <div class="mx-auto w-full max-w-[430px] px-5 pt-5">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-[13px] font-medium uppercase tracking-[0.18em] text-[#8D887F]">
                    settings
                </div>

                <h1 class="mt-3 text-[58px] font-semibold leading-[0.84] tracking-[-0.09em] text-[#171717]">
                    Notify<br>Me
                </h1>
            </div>

            <a
                href="{{ route('page-wishlists') }}"
                class="flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/70 text-[18px] text-[#171717] shadow-sm"
            >
                ×
            </a>
        </div>

        <div class="mt-7 space-y-3">
            <div class="rounded-[30px] bg-white/70 p-4 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <label class="flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Кто-то присоединился</span>
                    <input wire:model.defer="wishlist_joined" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Кто-то забронировал подарок</span>
                    <input wire:model.defer="item_claimed" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Кто-то отменил бронь</span>
                    <input wire:model.defer="item_unclaimed" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Обновления вишлистов</span>
                    <input wire:model.defer="wishlist_updated" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Напоминания о датах</span>
                    <input wire:model.defer="event_reminders" type="checkbox">
                </label>

                <label class="mt-2 flex min-h-[62px] items-center justify-between rounded-[22px] bg-[#F4F3EF] px-4">
                    <span class="text-[14px] font-medium">Информационные сообщения</span>
                    <input wire:model.defer="marketing" type="checkbox">
                </label>
            </div>

            <div class="rounded-[30px] bg-white/70 p-5 shadow-[0_12px_30px_rgba(0,0,0,0.04)]">
                <div class="text-[13px] text-[#77736B]">
                    Напоминать за
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach([30, 14, 7, 3, 1] as $day)
                        <button
                            type="button"
                            wire:click="toggleReminderDay({{ $day }})"
                            class="h-[42px] rounded-full px-5 text-[13px] font-medium {{ in_array($day, $reminder_days, true) ? 'bg-[#171717] text-white' : 'bg-[#F4F3EF] text-[#171717]' }}"
                        >
                            {{ $day }} дн.
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-[430px] px-5 pb-4" style="padding-bottom: max(16px, env(safe-area-inset-bottom));">
        <button
            wire:click="save"
            class="flex h-[70px] w-full items-center justify-center rounded-[28px] bg-[#171717] text-[15px] font-medium text-white shadow-[0_18px_45px_rgba(0,0,0,0.18)]"
        >
            Сохранить настройки
        </button>
    </div>
</div>