<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Planungsbereich. Wird nur gerendert, wenn Event.status es erlaubt — das
 * UX-Prinzip aus Spec Abschnitt 10 ("jeder Schritt wird erst sichtbar, wenn
 * er benoetigt wird") haengt an der Statusmaschine, nicht an einer Absicht.
 */
const props = defineProps({
  event: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  // Im Public-Kontext darf nur die eigene Zuweisung geaendert werden.
  participantToken: { type: String, default: null },
  me: { type: Object, default: null },
  canManage: { type: Boolean, default: false },
})

const emit = defineEmits(['updated', 'focus-change', 'error'])
const { t } = useI18n()

const busy = ref(false)
const newTask = ref({})
const newSection = ref('')

const readOnly = computed(() => ['closed', 'cancelled'].includes(props.event.status))

const sections = computed(() => props.event.plan_sections)

function tasksOf(sectionId) {
  return props.event.tasks.filter((task) => task.plan_section_id === sectionId)
}

const looseTasks = computed(() => props.event.tasks.filter((task) => !task.plan_section_id))

function suggestionsOf(section) {
  return props.event.task_suggestions?.[section.key] || []
}

async function call(method, url, payload) {
  busy.value = true
  try {
    const { data } = await window.axios[method](url, payload)
    emit('updated', data.event)
    return data
  } catch (e) {
    emit('error')
  } finally {
    busy.value = false
  }
}

function withToken(payload) {
  return props.participantToken ? { ...payload, token: props.participantToken } : payload
}

async function addTask(sectionId) {
  const title = (newTask.value[sectionId ?? 'none'] || '').trim()
  if (!title) return

  await call('post', `${props.baseUrl}/tasks`, withToken({ title, plan_section_id: sectionId }))
  newTask.value[sectionId ?? 'none'] = ''
}

async function toggleDone(task) {
  await call('patch', `${props.baseUrl}/tasks/${task.id}`, withToken({
    status: task.status === 'done' ? 'open' : 'done',
  }))
}

async function assign(task, participantId) {
  await call('patch', `${props.baseUrl}/tasks/${task.id}`, withToken({
    assignee_participant_id: participantId ? Number(participantId) : null,
  }))
}

async function claim(task) {
  if (!props.me) return
  await assign(task, task.assignee_participant_id === props.me.id ? null : props.me.id)
}

async function removeTask(task) {
  await call('delete', `${props.baseUrl}/tasks/${task.id}`, { data: withToken({}) })
}

async function adopt(section, titles) {
  await call('post', `${props.baseUrl}/tasks/adopt`, { titles, plan_section_id: section.id })
}

async function addSection() {
  const title = newSection.value.trim()
  if (!title) return
  await call('post', `${props.baseUrl}/sections`, { title })
  newSection.value = ''
}

async function removeSection(section) {
  await call('delete', `${props.baseUrl}/sections/${section.id}`)
}
</script>

<template>
  <section class="pl-card p-4 sm:p-5">
    <h2 class="font-display font-semibold">{{ t('manage.plan.title') }}</h2>

    <div v-for="section in sections" :key="section.id" class="mt-5">
      <div class="flex items-center justify-between gap-2">
        <h3 class="font-display text-sm font-semibold tracking-wide text-[var(--color-pl-muted)] uppercase">
          {{ section.title }}
        </h3>
        <button
          v-if="canManage && !readOnly"
          type="button"
          class="text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-no)]"
          :aria-label="t('common.delete')"
          @click="removeSection(section)"
        >
          ✕
        </button>
      </div>

      <ul class="mt-2 space-y-1.5">
        <li
          v-for="task in tasksOf(section.id)"
          :key="task.id"
          class="flex flex-wrap items-center gap-2 rounded-xl border border-[var(--color-pl-line)] px-3 py-2"
        >
          <input
            type="checkbox"
            :checked="task.status === 'done'"
            :disabled="busy || readOnly"
            class="h-4 w-4 shrink-0"
            :aria-label="task.title"
            @change="toggleDone(task)"
          />
          <span class="min-w-0 flex-1 text-sm" :class="{ 'text-[var(--color-pl-muted)] line-through': task.status === 'done' }">
            {{ task.title }}
          </span>

          <select
            v-if="canManage && !readOnly"
            class="rounded-lg border border-[var(--color-pl-line)] px-2 py-1 text-xs"
            :value="task.assignee_participant_id || ''"
            @change="assign(task, $event.target.value)"
          >
            <option value="">{{ t('manage.plan.unassigned') }}</option>
            <option v-for="p in event.participants" :key="p.id" :value="p.id">{{ p.display_name }}</option>
          </select>

          <template v-else>
            <span
              v-if="task.assignee_name"
              class="rounded-lg px-2 py-1 text-xs"
              :style="
                me && task.assignee_participant_id === me.id
                  ? { background: 'var(--color-pl-accent-soft)', color: 'var(--color-pl-accent-dark)' }
                  : { background: 'var(--color-pl-bg)', color: 'var(--color-pl-muted)' }
              "
            >
              {{ me && task.assignee_participant_id === me.id ? t('public.mine') : task.assignee_name }}
            </span>

            <button
              v-if="me && !readOnly && (!task.assignee_participant_id || task.assignee_participant_id === me.id)"
              type="button"
              class="rounded-lg px-2 py-1 text-xs font-semibold"
              :style="{ color: 'var(--color-pl-accent-dark)', background: 'var(--color-pl-accent-soft)' }"
              :disabled="busy"
              @click="claim(task)"
            >
              {{ task.assignee_participant_id === me.id ? t('public.release') : t('public.take') }}
            </button>
          </template>

          <button
            v-if="canManage && !readOnly"
            type="button"
            class="text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-no)]"
            :aria-label="t('common.delete')"
            @click="removeTask(task)"
          >
            ✕
          </button>
        </li>
      </ul>

      <!-- Vorschlaege: inaktive Liste mit "Uebernehmen" pro Zeile -->
      <div v-if="canManage && !readOnly && suggestionsOf(section).length" class="mt-2 flex flex-wrap items-center gap-1.5">
        <span class="text-xs text-[var(--color-pl-muted)]">{{ t('manage.plan.suggestions') }}:</span>
        <button
          v-for="title in suggestionsOf(section)"
          :key="title"
          type="button"
          class="rounded-lg border border-dashed border-[var(--color-pl-line)] px-2 py-1 text-xs text-[var(--color-pl-muted)] hover:border-[var(--color-pl-accent)] hover:text-[var(--color-pl-accent-dark)]"
          :disabled="busy"
          @click="adopt(section, [title])"
        >
          + {{ title }}
        </button>
        <button
          v-if="suggestionsOf(section).length > 1"
          type="button"
          class="text-xs font-semibold text-[var(--color-pl-accent)] hover:underline"
          :disabled="busy"
          @click="adopt(section, suggestionsOf(section))"
        >
          {{ t('manage.plan.adopt_all') }}
        </button>
      </div>

      <div v-if="!readOnly" class="mt-2 flex gap-2">
        <input
          v-model="newTask[section.id]"
          class="pl-input py-1.5 text-sm"
          :placeholder="t('manage.plan.task_placeholder')"
          @focus="emit('focus-change', true)"
          @blur="emit('focus-change', false)"
          @keyup.enter="addTask(section.id)"
        />
        <button type="button" class="pl-btn pl-btn-ghost px-3 py-1.5 text-sm" :disabled="busy" @click="addTask(section.id)">
          +
        </button>
      </div>
    </div>

    <!-- Aufgaben ohne Bereich (z.B. nach dem Loeschen einer Sektion) -->
    <div v-if="looseTasks.length || !sections.length" class="mt-5">
      <h3 v-if="sections.length" class="font-display text-sm font-semibold tracking-wide text-[var(--color-pl-muted)] uppercase">
        {{ t('manage.plan.other_tasks') }}
      </h3>

      <ul class="mt-2 space-y-1.5">
        <li
          v-for="task in looseTasks"
          :key="task.id"
          class="flex flex-wrap items-center gap-2 rounded-xl border border-[var(--color-pl-line)] px-3 py-2"
        >
          <input
            type="checkbox"
            :checked="task.status === 'done'"
            :disabled="busy || readOnly"
            class="h-4 w-4 shrink-0"
            :aria-label="task.title"
            @change="toggleDone(task)"
          />
          <span class="min-w-0 flex-1 text-sm" :class="{ 'text-[var(--color-pl-muted)] line-through': task.status === 'done' }">
            {{ task.title }}
          </span>

          <select
            v-if="canManage && !readOnly"
            class="rounded-lg border border-[var(--color-pl-line)] px-2 py-1 text-xs"
            :value="task.assignee_participant_id || ''"
            @change="assign(task, $event.target.value)"
          >
            <option value="">{{ t('manage.plan.unassigned') }}</option>
            <option v-for="p in event.participants" :key="p.id" :value="p.id">{{ p.display_name }}</option>
          </select>

          <template v-else>
            <span v-if="task.assignee_name" class="rounded-lg bg-[var(--color-pl-bg)] px-2 py-1 text-xs text-[var(--color-pl-muted)]">
              {{ me && task.assignee_participant_id === me.id ? t('public.mine') : task.assignee_name }}
            </span>
            <button
              v-if="me && !readOnly && (!task.assignee_participant_id || task.assignee_participant_id === me.id)"
              type="button"
              class="rounded-lg px-2 py-1 text-xs font-semibold"
              :style="{ color: 'var(--color-pl-accent-dark)', background: 'var(--color-pl-accent-soft)' }"
              :disabled="busy"
              @click="claim(task)"
            >
              {{ task.assignee_participant_id === me.id ? t('public.release') : t('public.take') }}
            </button>
          </template>

          <button
            v-if="canManage && !readOnly"
            type="button"
            class="text-xs text-[var(--color-pl-muted)] hover:text-[var(--color-pl-no)]"
            :aria-label="t('common.delete')"
            @click="removeTask(task)"
          >
            ✕
          </button>
        </li>
      </ul>

      <div v-if="!readOnly" class="mt-2 flex gap-2">
        <input
          v-model="newTask['none']"
          class="pl-input py-1.5 text-sm"
          :placeholder="canManage ? t('manage.plan.task_placeholder') : t('public.add_own')"
          @focus="emit('focus-change', true)"
          @blur="emit('focus-change', false)"
          @keyup.enter="addTask(null)"
        />
        <button type="button" class="pl-btn pl-btn-ghost px-3 py-1.5 text-sm" :disabled="busy" @click="addTask(null)">+</button>
      </div>
    </div>

    <div v-if="canManage && !readOnly" class="mt-5 flex gap-2 border-t border-[var(--color-pl-line)] pt-4">
      <input
        v-model="newSection"
        class="pl-input py-1.5 text-sm"
        :placeholder="t('manage.plan.section_placeholder')"
        @focus="emit('focus-change', true)"
        @blur="emit('focus-change', false)"
        @keyup.enter="addSection"
      />
      <button type="button" class="pl-btn pl-btn-ghost whitespace-nowrap px-3 py-1.5 text-sm" :disabled="busy" @click="addSection">
        {{ t('manage.plan.add_section') }}
      </button>
    </div>
  </section>
</template>
