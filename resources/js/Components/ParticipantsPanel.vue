<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
})

const emit = defineEmits(['updated', 'focus-change', 'error', 'flash'])
const { t } = useI18n()

const busy = ref(false)
const inviteInput = ref('')
const mergeSource = ref(null)

async function call(method, url, payload) {
  busy.value = true
  try {
    const { data } = await window.axios[method](url, payload)
    if (data.event) emit('updated', data.event)
    return data
  } catch (e) {
    emit('error')
  } finally {
    busy.value = false
  }
}

async function toggleRequired(participant) {
  await call('patch', `${props.baseUrl}/participants/${participant.id}`, {
    is_required: !participant.is_required,
  })
}

async function rename(participant, event) {
  const name = event.target.value.trim()
  if (!name || name === participant.display_name) return
  await call('patch', `${props.baseUrl}/participants/${participant.id}`, { display_name: name })
}

async function remove(participant) {
  await call('delete', `${props.baseUrl}/participants/${participant.id}`)
}

async function merge(participant, targetId) {
  if (!targetId) return
  await call('post', `${props.baseUrl}/participants/${participant.id}/merge`, {
    into_participant_id: Number(targetId),
  })
  mergeSource.value = null
}

async function invite() {
  const emails = inviteInput.value
    .split(/[,;\s]+/)
    .map((value) => value.trim())
    .filter(Boolean)

  if (!emails.length) return

  const data = await call('post', `${props.baseUrl}/invite`, { emails })
  if (data) {
    inviteInput.value = ''
    emit('flash', t('manage.participants.invited', { count: data.sent }))
  }
}
</script>

<template>
  <section class="pl-card p-4 sm:p-5">
    <h2 class="font-display font-semibold">{{ t('manage.participants.title') }}</h2>

    <p v-if="!event.participants.length" class="mt-3 text-sm text-[var(--color-pl-muted)]">
      {{ t('manage.participants.empty') }}
    </p>

    <ul v-else class="mt-3 divide-y divide-[var(--color-pl-line)]">
      <li v-for="participant in event.participants" :key="participant.id" class="py-2.5">
        <div class="flex flex-wrap items-center gap-2">
          <input
            :value="participant.display_name"
            class="min-w-0 flex-1 rounded-lg border border-transparent px-1.5 py-1 text-sm hover:border-[var(--color-pl-line)] focus:border-[var(--color-pl-accent)] focus:outline-none"
            maxlength="80"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false); rename(participant, $event)"
            @keyup.enter="$event.target.blur()"
          />

          <button
            type="button"
            class="rounded-lg border px-2 py-1 text-xs"
            :title="t('manage.participants.required_hint')"
            :style="
              participant.is_required
                ? { background: 'var(--color-pl-ink)', borderColor: 'var(--color-pl-ink)', color: '#fff' }
                : { borderColor: 'var(--color-pl-line)', color: 'var(--color-pl-muted)' }
            "
            :disabled="busy"
            @click="toggleRequired(participant)"
          >
            ★ {{ t('manage.participants.required') }}
          </button>

          <button
            type="button"
            class="rounded-lg px-2 py-1 text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-accent)]"
            @click="mergeSource = mergeSource === participant.id ? null : participant.id"
          >
            ⇄
          </button>

          <button
            type="button"
            class="rounded-lg px-2 py-1 text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-no)]"
            :disabled="busy"
            :aria-label="t('manage.participants.remove')"
            @click="remove(participant)"
          >
            ✕
          </button>
        </div>

        <p class="mt-0.5 pl-1.5 text-xs text-[var(--color-pl-muted)]">
          <span v-if="participant.email">{{ participant.email }} · </span>
          {{ t('manage.participants.answered', { count: participant.answered_count, total: event.date_options.length }) }}
        </p>

        <div v-if="mergeSource === participant.id" class="mt-2 pl-1.5">
          <label class="text-xs text-[var(--color-pl-muted)]">{{ t('manage.participants.merge_hint') }}</label>
          <select class="pl-input mt-1 text-sm" @change="merge(participant, $event.target.value)">
            <option value="">{{ t('manage.participants.merge') }}</option>
            <option
              v-for="other in event.participants.filter((p) => p.id !== participant.id)"
              :key="other.id"
              :value="other.id"
            >
              {{ other.display_name }}
            </option>
          </select>
        </div>
      </li>
    </ul>

    <div class="mt-4 border-t border-[var(--color-pl-line)] pt-4">
      <label class="text-xs font-semibold text-[var(--color-pl-muted)]" for="p-invite">
        {{ t('manage.participants.invite') }}
      </label>
      <div class="mt-1 flex flex-col gap-2 sm:flex-row">
        <input
          id="p-invite"
          v-model="inviteInput"
          class="pl-input text-sm"
          :placeholder="t('manage.participants.invite_placeholder')"
          @focus="emit('focus-change', true)"
          @blur="emit('focus-change', false)"
        />
        <button type="button" class="pl-btn pl-btn-ghost whitespace-nowrap text-sm" :disabled="busy" @click="invite">
          {{ t('manage.participants.invite_send') }}
        </button>
      </div>
    </div>
  </section>
</template>
