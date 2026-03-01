<div class="w-full max-w-sm">
    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="invite-code-display">
        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Invite Code</span>
    </label>

    <input
        id="invite-code-display"
        type="text"
        readonly
        value="{{ $inviteCode ?: 'No code generated yet' }}"
        class="fi-input block w-full rounded-lg border-none bg-gray-100 px-3 py-2 text-sm text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10"
    />
</div>
