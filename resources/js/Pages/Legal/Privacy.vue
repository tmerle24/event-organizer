<script setup>
import { onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Logo from '@/Components/Logo.vue'
import Footer from '@/Components/Footer.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'

const { t } = useI18n()

// JS-Schutz gegen E-Mail-Harvester, siehe Impressum.
const email = ref('')

onMounted(() => {
  // String.fromCharCode(64) = "@" — als Literal würde der Bundler die
  // Adresse beim Build wieder zu einem fertigen String zusammenfalten.
  email.value = ['hello', 'orgdate.com'].join(String.fromCharCode(64))
})
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <Head :title="t('legal.privacy_title')" />

    <header class="mx-auto flex w-full max-w-2xl items-center justify-between px-6 py-5">
      <a href="/" class="flex"><Logo /></a>
      <LanguageSwitcher />
    </header>

    <main class="mx-auto w-full max-w-2xl flex-1 px-6 py-6">
      <h1 class="od-h1">{{ t('legal.privacy_title') }}</h1>

      <div class="od-measure mt-8 space-y-7">
        <section>
          <h2 class="od-h3">1. Verantwortlicher</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Till Merlé<br />
            Birkenstr. 19, 61440 Oberursel, Germany<br />
            E-Mail:
            <a v-if="email" :href="`mailto:${email}`" class="underline" style="color: var(--od-violet)">{{ email }}</a>
          </p>
        </section>

        <section>
          <h2 class="od-h3">2. Was ORGDATE speichert</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Beim Anlegen eines Events speichern wir den von dir eingegebenen Text, den daraus
            erzeugten Titel, die Terminvorschläge und – falls angegeben – Ort und Beschreibung.
            Wer sich über den geteilten Link einträgt, hinterlässt einen Namen (Pflicht), seine
            Verfügbarkeiten und optional eine E-Mail-Adresse. Die Adresse nutzen wir nur für
            Nachrichten zu diesem Event; wer das Event organisiert, bekommt sie nicht zu sehen. Im
            Planungsbereich kommen Aufgaben und deren Zuordnung hinzu. Ein Benutzerkonto gibt es
            nicht.
          </p>
          <p class="od-small mt-2" style="color: var(--od-slate)">
            Wer sich eingetragen hat, sieht die Namen der anderen Teilnehmenden, ihre Zusagen und
            Vielleicht-Antworten sowie übernommene Aufgaben. Absagen erscheinen dort nur als Anzahl.
            Wer das Event organisiert, sieht alle Antworten. Vor dem Eintragen zeigt die Seite nur
            Anzahlen, keine Namen. Die E-Mail-Adresse ist für niemanden sichtbar.
          </p>
        </section>

        <section>
          <h2 class="od-h3">3. Server-Logfiles</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Beim Erstellen eines Events speichern wir die IP-Adresse der erstellenden Person zur
            Missbrauchsprävention (creator_ip). Der Hosting-Provider erzeugt zusätzlich technische
            Server-Logfiles (aufgerufene Seite, Zeitpunkt, IP-Adresse), die automatisiert entstehen.
            (OVH Cloud, Deutschland)
          </p>
        </section>

        <section>
          <h2 class="od-h3">4. Lokale Speicherung im Browser (LocalStorage)</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            ORGDATE verzichtet bewusst auf ein Login-System. Stattdessen werden zufällig erzeugte
            Tokens (Verwaltungs-Token, Teilnahme-Token) im LocalStorage deines Browsers gespeichert.
            Dazu kommen deine Sprachwahl, eine Liste der von dir erstellten Events und der Name,
            mit dem du dich zuletzt eingetragen hast, damit er beim nächsten Event schon im Feld
            steht – all das verlässt dein Gerät nicht. Es werden keine Analyse- oder Werbe-Cookies gesetzt.
            ORGDATE nutzt kein Tracking und bindet keine Inhalte von Drittanbietern ein: keine
            Analyse-Tools, keine Werbenetzwerke, keine externen Schriften oder Skripte. Die Schrift
            wird von unserem eigenen Server geladen.
          </p>
        </section>

        <section>
          <h2 class="od-h3">5. KI-gestützte Texterkennung</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Um aus deinem Satz Titel, Zeitraum und Terminvorschläge zu erzeugen, kann dieser Text an
            die Claude-API von Anthropic (Anthropic PBC, USA) übermittelt werden. Übertragen wird
            ausschließlich der von dir eingegebene Freitext, keine Namen und keine E-Mail-Adressen
            der Teilnehmenden. Die Übermittlung in die USA erfolgt auf Grundlage der
            EU-Standardvertragsklauseln. Ist die Funktion deaktiviert oder der Dienst nicht
            erreichbar, verarbeitet ORGDATE den Text ausschließlich auf dem eigenen Server.
            Schreib bitte keine sensiblen Angaben in dieses Feld.
          </p>
        </section>

        <section>
          <h2 class="od-h3">6. E-Mails</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Wir versenden ausschließlich Nachrichten, die zum Ablauf des Events gehören:
            Einladung, Bestätigung des Termins, Änderungen an Termin oder Ort nach der Bestätigung,
            Absage und deren Rücknahme sowie auf Anforderung den Verwaltungslink. Kein Newsletter,
            keine Werbung. Eine E-Mail-Adresse anzugeben ist für Teilnehmende freiwillig; ohne
            Adresse funktioniert alles außer der Benachrichtigung. Jede dieser Mails enthält einen
            Link, über den die eigene Adresse sofort gelöscht wird.
          </p>
          <p class="od-small mt-2" style="color: var(--od-slate)">
            Adressen, an die über ORGDATE eine Einladung oder der Verwaltungslink verschickt wird,
            verwenden wir nur für diesen einen Versand und speichern sie nicht. Damit dieselbe
            Einladung nicht zweimal verschickt wird, bleibt lediglich ein nicht umkehrbarer Prüfwert (Hash) zurück, aus dem
            sich die Adresse nicht wiederherstellen lässt. Auch im Versandprotokoll der übrigen
            Mails steht keine Adresse.
          </p>
        </section>

        <section>
          <h2 class="od-h3">7. Speicherdauer</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Ein Event wird mitsamt Terminen, Antworten, Aufgaben und Teilnehmerdaten automatisch
            gelöscht, wenn zwölf Monate lang keine Aktivität stattgefunden hat. Wer das Event
            erstellt hat, kann es jederzeit vollständig löschen. Teilnehmende können ihre eigene
            Teilnahme inklusive aller Antworten selbst entfernen oder nur ihre E-Mail-Adresse über
            den Link in jeder Mail löschen.
          </p>
        </section>

        <section>
          <h2 class="od-h3">8. Deine Rechte</h2>
          <p class="od-small mt-1" style="color: var(--od-slate)">
            Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung,
            Datenübertragbarkeit sowie Widerspruch gegen die Verarbeitung deiner personenbezogenen
            Daten. Wende dich dafür an die oben genannte Kontaktadresse. Außerdem besteht ein
            Beschwerderecht bei einer Datenschutzaufsichtsbehörde.
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
