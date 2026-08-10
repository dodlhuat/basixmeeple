<script setup lang="ts">
import { ApiError } from '~/utils/apiClient'

definePageMeta({ layout: 'auth' })
useHead({ title: 'Anmelden – BasixMeeple' })

const { login } = useAuth()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

async function onSubmit() {
  error.value = null
  submitting.value = true

  try {
    await login(email.value, password.value)
    await navigateTo('/collections')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Anmeldung fehlgeschlagen.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="auth-screen">
    <form class="card auth-card" @submit.prevent="onSubmit">
      <h1>BasixMeeple</h1>
      <p class="secondary-text">Melde dich an, um auf deine Sammlung zuzugreifen.</p>

      <div class="form-group">
        <label for="email">E-Mail</label>
        <input id="email" v-model="email" type="email" autocomplete="username" required />
      </div>

      <div class="form-group">
        <label for="password">Passwort</label>
        <input id="password" v-model="password" type="password" autocomplete="current-password" required />
      </div>

      <p v-if="error" class="field-error">{{ error }}</p>

      <button type="submit" class="button button-primary" :disabled="submitting">
        {{ submitting ? 'Anmelden …' : 'Anmelden' }}
      </button>
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
