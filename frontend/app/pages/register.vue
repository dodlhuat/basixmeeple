<script setup lang="ts">
import { ApiError } from '~/utils/apiClient'

definePageMeta({ layout: 'auth' })
useHead({ title: 'Registrieren – BasixMeeple' })

const route = useRoute()
const { register } = useAuth()

const token = ref(typeof route.query.token === 'string' ? route.query.token : '')
const name = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

async function onSubmit() {
  error.value = null
  submitting.value = true

  try {
    await register({
      token: token.value,
      name: name.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await navigateTo('/collections')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Registrierung fehlgeschlagen.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="auth-screen">
    <form class="card auth-card" @submit.prevent="onSubmit">
      <h1>Registrieren</h1>
      <p class="secondary-text">
        Du wurdest zu einer Sammlung eingeladen. Vervollständige dein Konto, um beizutreten.
      </p>

      <div class="form-group">
        <label for="token">Einladungscode</label>
        <input id="token" v-model="token" type="text" required />
      </div>

      <div class="form-group">
        <label for="name">Name</label>
        <input id="name" v-model="name" type="text" autocomplete="name" required />
      </div>

      <div class="form-group">
        <label for="password">Passwort</label>
        <input id="password" v-model="password" type="password" autocomplete="new-password" required />
      </div>

      <div class="form-group">
        <label for="password-confirmation">Passwort bestätigen</label>
        <input
          id="password-confirmation"
          v-model="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          required
        />
      </div>

      <p v-if="error" class="field-error">{{ error }}</p>

      <button type="submit" class="button button-primary" :disabled="submitting">
        {{ submitting ? 'Registrieren …' : 'Konto erstellen' }}
      </button>

      <NuxtLink to="/login" class="secondary-text">Schon registriert? Anmelden</NuxtLink>
    </form>
  </section>
</template>

<style lang="scss" scoped>
.auth-screen {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.auth-card {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 380px;
}

.field-error {
  color: var(--error);
  font-size: 0.875rem;
}
</style>
