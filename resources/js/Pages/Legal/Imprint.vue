<script setup>
import { onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { t } = useI18n()

/*
 * JS-Schutz: E-Mail und Telefon werden erst client-seitig zusammengesetzt,
 * damit einfache Harvester nichts im ausgelieferten HTML finden. Die
 * Bestandteile stehen im Quelltext nie als fertiger String.
 */
const email = ref('')
const phone = ref('')

onMounted(() => {
  // String.fromCharCode(64) = "@" — als Literal würde der Bundler die
  // Adresse beim Build wieder zu einem fertigen String zusammenfalten.
  email.value = ['hello', 'orgdate.com'].join(String.fromCharCode(64))

  phone.value = ['+49', '170', '481', '4147'].join(String.fromCharCode(32))
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <Head :title="t('legal.imprint_title')" />

    <header class="mx-auto flex w-full max-w-2xl items-center justify-between px-6 py-5">
      <a href="/" class="flex"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto w-full max-w-2xl flex-1 px-6 py-6">
      <h1 class="od-h1">{{ t('legal.imprint_title') }}</h1>

      <div class="od-measure mt-8 space-y-7">
        <section>
          <h2 class="od-h3">Anbieter</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Till Merlé<br />
            Birkenstr. 19<br />
            61440 Oberursel<br />
            Germany
          </p>
        </section>

        <section>
          <h2 class="od-h3">Kontakt</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            E-Mail:
            <a v-if="email" :href="`mailto:${email}`" class="underline" style="color: var(--od-violet)">{{ email }}</a>
            <span v-else>–</span>
            <br />
            Telefon:
            <a
              v-if="phone"
              :href="`tel:${phone.replace(/\s/g, '')}`"
              class="underline"
              style="color: var(--od-violet)"
            >{{ phone }}</a>
            <span v-else>–</span>
          </p>
        </section>

        <section>
          <h2 class="od-h3">Vertretungsberechtigt</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">Till Merlé</p>
        </section>

        <section>
          <h2 class="od-h3">Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Till Merlé, Birkenstr. 19, 61440 Oberursel, Germany
          </p>
        </section>

        <section>
          <h2 class="od-h3">Streitschlichtung</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
            <a
              href="https://ec.europa.eu/consumers/odr/"
              target="_blank"
              rel="noopener"
              class="underline"
              style="color: var(--od-violet)"
            >ec.europa.eu/consumers/odr/</a>. Wir sind nicht verpflichtet und nicht bereit, an
            Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
          </p>
        </section>
      </div>

      <a href="/" class="od-small mt-10 inline-block underline" style="color: var(--od-violet)">
        ← {{ t('legal.back') }}
      </a>
    </main>

    <Footer />
  </div>
</template>
