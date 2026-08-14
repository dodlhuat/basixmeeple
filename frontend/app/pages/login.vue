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
  <form class="card auth-card" @submit.prevent="onSubmit">
    <div class="auth-card-intro">
      <span class="auth-icon-badge">
        <svg class="icon-svg"><use :href="iconHref('login')" /></svg>
      </span>
      <div class="auth-card-intro-text">
        <h1>BasixMeeple</h1>
        <p class="text-secondary">Melde dich an, um auf deine Sammlung zuzugreifen.</p>
      </div>
    </div>

    <div class="form-group" :class="{ error: !!error }">
      <label for="email">E-Mail</label>
      <input id="email" v-model="email" type="email" autocomplete="username" required />
    </div>

    <div class="form-group" :class="{ error: !!error }">
      <label for="password">Passwort</label>
      <input id="password" v-model="password" type="password" autocomplete="current-password" required />
      <p v-if="error" class="form-hint">{{ error }}</p>
    </div>

    <button
      type="submit"
      class="button button-primary button-lg auth-submit"
      :class="{ 'is-loading': submitting }"
      :disabled="submitting"
    >
      {{ submitting ? 'Anmelden …' : 'Anmelden' }}
    </button>
  </form>
</template>

<style lang="scss" scoped>
.auth-card {
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 2rem 1.75rem;
  border-radius: 1.5rem;
  box-shadow:
    0 20px 48px -20px rgba(0, 0, 0, 0.28),
    0 2px 8px rgba(0, 0, 0, 0.06);

  // One composed "the card just arrived" moment — matching the stats
  // dashboard's stagger language, kept restrained to the card's top-level
  // groups rather than every individual label/input.
  > * {
    animation: auth-rise-in 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
  }

  @for $i from 1 through 6 {
    > *:nth-child(#{$i}) {
      animation-delay: #{60 * ($i - 1)}ms;
    }
  }
}

@keyframes auth-rise-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.auth-card-intro {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
}

.auth-card-intro-text {
  min-width: 0;

  h1 {
    margin: 0 0 0.2rem;
    font-size: 1.625rem;
  }

  p {
    margin: 0;
  }
}

.auth-icon-badge {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  background: var(--accent-color-tint);
  color: var(--accent-color);
  margin-top: 0.1rem;
}

.auth-submit {
  width: 100%;
  margin-top: 0.25rem;
}

@media (prefers-reduced-motion: reduce) {
  .auth-card > * {
    animation: none;
  }
}
</style>
