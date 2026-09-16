<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatShort } from '@/composables/useDateFormat'
import { ANSWERS } from '@/composables/useMatch'

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

// ab so vielen Terminen nur noch Summen, sonst wird die Zeile zu lang
const MAX_INLINE_DATES = 4

const chronological = computed(() =>
  [...props.event.date_options].sort((a, b) => (a.starts_at_utc ?? a.day).localeCompare(b.starts_at_utc ?? b.day))
)

const decidedOption = computed(
  () => props.event.date_options.find((option) => option.id === props.event.decided_option_id) ?? null
)

function answerOf(option, participant) {
  return option.votes?.[participant.id] ?? 'open'
}

function answerLine(participant) {
  if (decidedOption.value) {
    return { type: 'decided', answer: answerOf(decidedOption.value, participant) }
  }

  if (chronological.value.length <= MAX_INLINE_DATES) {
    return {
      type: 'dates',
      items: chronological.value.map((option) => ({ id: option.id, label: formatShort(option), answer: answerOf(option, participant) })),
    }
  }

  const sums = { yes: 0, maybe: 0, no: 0, open: 0 }
  chronological.value.forEach((option) => sums[answerOf(option, participant)]++)
  return { type: 'sums', items: Object.entries(sums).filter(([, count]) => count).map(([answer, count]) => ({ answer, count })) }
}

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

function print() {
  window.print()
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
    <div class="flex items-center justify-between gap-2">
      <h2 class="font-display font-semibold">{{ t('manage.participants.title') }}</h2>
      <button
        v-if="event.participants.length"
        type="button"
        class="-my-1 flex h-9 w-9 items-center justify-center rounded-lg text-[var(--od-slate)] hover:bg-[var(--od-mist)] hover:text-[var(--od-violet)]"
        :title="t('manage.print.button')"
        :aria-label="t('manage.print.button')"
        @click="print"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M7 9V3h10v6" />
          <rect x="3" y="9" width="18" height="8" rx="2" />
          <path d="M7 14h10v7H7z" />
        </svg>
      </button>
    </div>

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

          <span
            v-if="participant.has_email"
            class="inline-flex px-1 text-[var(--od-slate)]"
            :title="t('manage.participants.gets_updates')"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="5" width="18" height="14" rx="2" />
              <path d="m3.5 6.5 8.5 6.5 8.5-6.5" />
            </svg>
            <span class="sr-only">{{ t('manage.participants.gets_updates') }}</span>
          </span>

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

        <p
          v-if="hasPolling && event.date_options.length"
          class="mt-0.5 flex flex-wrap items-center gap-x-1.5 pl-1.5 text-xs text-[var(--od-slate)]"
        >
          <template v-if="hasPolling && event.date_options.length">
            <!-- nach der Festlegung zaehlt nur noch: kommt die Person? -->
            <span
              v-if="answerLine(participant).type === 'decided'"
              :style="{ color: ANSWERS[answerLine(participant).answer].color }"
            >
              {{ t(`manage.participants.decided_answer.${answerLine(participant).answer}`) }}
            </span>

            <template v-else-if="answerLine(participant).type === 'dates'">
              <template v-for="(item, index) in answerLine(participant).items" :key="item.id">
                <span v-if="index" aria-hidden="true">·</span>
                <span class="whitespace-nowrap">
                  {{ item.label }}
                  <span :style="{ color: ANSWERS[item.answer].color }" :title="t(`manage.counts.${item.answer}`, 1)">{{ ANSWERS[item.answer].icon }}</span>
                </span>
              </template>
            </template>

            <template v-else>
              <template v-for="(item, index) in answerLine(participant).items" :key="item.answer">
                <span v-if="index" aria-hidden="true">·</span>
                <span class="whitespace-nowrap" :title="t(`manage.counts.${item.answer}`, item.count)">
                  {{ item.count }} <span :style="{ color: ANSWERS[item.answer].color }">{{ ANSWERS[item.answer].icon }}</span>
                </span>
              </template>
            </template>
          </template>
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

    <!-- Linie direkt unter der Liste, sonst doppelter Abstand zur letzten Zeile -->
    <div class="border-t border-[var(--od-line)] pt-4" :class="{ 'mt-4': !event.participants.length }">
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
