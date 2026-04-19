<div
    x-data="{
        show: false,
        message: '',
        type: 'success',
        timeout: null,
        open(payload) {
            this.message = payload.message || '';
            this.type = payload.type || 'success';
            this.show = true;

            clearTimeout(this.timeout);

            this.timeout = setTimeout(() => {
                this.show = false;
            }, payload.duration || 2500);
        }
    }"
    x-on:toast.window="open($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-center px-4"
>
    <div
        x-show="show"
        x-transition
        class="pointer-events-auto w-full max-w-md rounded-2xl px-4 py-3 text-sm font-medium shadow-xl"
        :class="{
            'bg-[#1f2a37] text-white': type === 'success',
            'bg-[#fee2e2] text-[#991b1b]': type === 'error',
            'bg-[#fef3c7] text-[#92400e]': type === 'warning',
        }"
    >
        <span x-text="message"></span>
    </div>
</div>