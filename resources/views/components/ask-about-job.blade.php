@props(['job'])

{{--
    Collapsible "Ask about this role" chat. Unlike the other AI affordances
    on this page (match panel, similar jobs), this widget is NOT hidden when
    AI is off — AskAboutJobService always returns a fixed, honest message in
    that state instead of the endpoint 404ing, so the chat stays usable and
    predictable regardless of provider config.
--}}
<div
    x-data="{
        open: false,
        question: '',
        loading: false,
        error: null,
        messages: [],
        send() {
            const question = this.question.trim();
            if (! question || this.loading) return;

            this.messages.push({ role: 'user', content: question });
            const history = this.messages.slice(0, -1).slice(-12);
            this.question = '';
            this.loading = true;
            this.error = null;

            fetch('{{ route('jobs.ask', $job) }}', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ question, history }),
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => {
                    this.messages.push({ role: 'assistant', content: data.answer });
                })
                .catch(() => {
                    this.error = 'Something went wrong. Please try again.';
                    this.messages.pop();
                })
                .finally(() => {
                    this.loading = false;
                    this.$nextTick(() => this.scrollToBottom());
                });
        },
        scrollToBottom() {
            if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
        },
    }"
    class="rounded-2xl border border-gray-200 bg-white shadow-soft overflow-hidden"
>
    <button
        type="button"
        @click="open = !open; $nextTick(() => scrollToBottom())"
        class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left"
    >
        <span class="flex items-center gap-2 font-bold text-gray-900">
            <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 0c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            Ask about this role
        </span>
        <svg class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition class="border-t border-gray-100 px-5 py-4 space-y-4">
        <p class="text-xs text-gray-500">Answers are grounded in this listing and the company profile only.</p>

        <div x-show="messages.length" x-ref="scroll" class="max-h-72 overflow-y-auto space-y-2.5 pr-1">
            <template x-for="(msg, i) in messages" :key="i">
                <div
                    :class="msg.role === 'user' ? 'ml-auto bg-brand-600 text-white' : 'bg-gray-100 text-gray-800'"
                    class="max-w-[85%] w-fit rounded-xl px-3.5 py-2 text-sm whitespace-pre-line"
                >
                    <span x-text="msg.content"></span>
                </div>
            </template>
            <div x-show="loading" class="max-w-[85%] w-fit rounded-xl bg-gray-100 px-3.5 py-2 text-sm text-gray-500">
                Thinking…
            </div>
        </div>

        <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

        <form @submit.prevent="send" class="flex gap-2">
            <input
                type="text"
                x-model="question"
                maxlength="500"
                placeholder="e.g. Is this role remote?"
                class="flex-1 rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500"
            />
            <button
                type="submit"
                :disabled="loading || !question.trim()"
                class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50 disabled:pointer-events-none"
            >
                Ask
            </button>
        </form>
    </div>
</div>
