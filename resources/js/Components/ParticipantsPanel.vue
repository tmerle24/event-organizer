<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
})

const emit = defineEmits(['updated', 'focus-change', 'error', 'flash'])
const { t } = useI18n()

const busy = ref(false)

/** Nur bei Terminfindung: ohne Abstimmung gibt es nichts zu beantworten und
 *  "Pflicht" steuert nichts (es wirkt allein im Ranking). */
const hasPolling = computed(() => ['dates', 'both'].includes(props.event.mode))
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
    emit('flash', t('manage.participants.invited', data.sent))
  }
}
</script>

<template>
  <section class="od-card p-4 sm:p-5">
    <h2 class="font-display font-semibold">{{ t('manage.participants.title') }}</h2>

    <p v-if="!event.participants.length" class="mt-3 text-sm text-[var(--od-slate)]">
      {{ t('manage.participants.empty') }}
    </p>

    <ul v-else class="mt-3 divide-y divide-[var(--od-line)]">
      <li v-for="participant in event.participants" :key="participant.id" class="py-2.5">
        <div class="flex flex-wrap items-center gap-2">
          <input
            :value="participant.display_name"
            class="min-w-0 flex-1 rounded-lg border border-transparent px-1.5 py-1 text-sm hover:border-[var(--od-line)] focus:border-[var(--od-violet)] focus:outline-none"
            maxlength="80"
            @focus="emit('focus-change', true)"
            @blur="emit('focus-change', false); rename(participant, $event)"
            @keyup.enter="$event.target.blur()"
          />

          <button
            v-if="hasPolling"
            type="button"
            class="rounded-lg border px-2 py-1 text-xs"
            :title="t('manage.participants.required_hint')"
            :style="
              participant.is_required
                ? { background: 'var(--od-ink)', borderColor: 'var(--od-ink)', color: '#fff' }
                : { borderColor: 'var(--od-line)', color: 'var(--od-slate)' }
            "
            :disabled="busy"
            @click="toggleRequired(participant)"
          >
            ★ {{ t('manage.participants.required') }}
          </button>

          <button
            type="button"
            class="rounded-lg px-2 py-1 text-xs text-[var(--od-slate)] hover:text-[var(--od-violet)]"
            :title="t('manage.participants.merge_hint')"
            :aria-label="t('manage.participants.merge_hint')"
            @click="mergeSource = mergeSource === participant.id ? null : participant.id"
          >
            ⇄
          </button>

          <button
            type="button"
            class="rounded-lg px-2 py-1 text-xs text-[var(--od-slate)] hover:text-[var(--od-slate)]"
            :disabled="busy"
            :aria-label="t('manage.participants.remove')"
            @click="remove(participant)"
          >
            ✕
          </button>
        </div>

        <p v-if="participant.email || hasPolling" class="mt-0.5 pl-1.5 text-xs text-[var(--od-slate)]">
          <span v-if="participant.email">{{ participant.email }}</span>
          <span v-if="participant.email && hasPolling"> · </span>
          <span v-if="hasPolling">
            {{ t('manage.participants.answered', { count: participant.answered_count, total: event.date_options.length }) }}
          </span>
        </p>

        <div v-if="mergeSource === participant.id" class="mt-2 pl-1.5">
          <label class="text-xs text-[var(--od-slate)]">{{ t('manage.participants.merge_hint') }}</label>
          <select class="od-input mt-1 text-sm" @change="merge(participant, $event.target.value)">
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

    <div class="mt-4 border-t border-[var(--od-line)] pt-4">
      <label class="text-xs font-semibold text-[var(--od-slate)]" for="p-invite">
        {{ t('manage.participants.invite') }}
      </label>
      <div class="mt-1 flex flex-col gap-2 sm:flex-row">
        <input
          id="p-invite"
          v-model="inviteInput"
          class="od-input text-sm"
          :placeholder="t('manage.participants.invite_placeholder')"
          @focus="emit('focus-change', true)"
          @blur="emit('focus-change', false)"
        />
        <button type="button" class="od-btn od-btn-ghost whitespace-nowrap text-sm" :disabled="busy" @click="invite">
          {{ t('manage.participants.invite_send') }}
        </button>
      </div>
    </div>
  </section>
</template>
