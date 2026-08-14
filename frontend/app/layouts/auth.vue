<script setup lang="ts">
// Shared "front door" chrome for /login and /register. Built once here
// rather than duplicated in both pages: a brand atmosphere panel (a small
// game-table motif assembled entirely from basix's own icon sprite — no new
// image assets) plus a content pane that hosts each page's own card.
//
// The atmosphere panel is deliberately always dark (var(--primary-dark)),
// regardless of the site's light/dark theme — a fixed brand identity rather
// than a themed surface, so it must not use theme-flipping tokens like
// --primary-text/--primary-light for its own text/icon colors (those swap
// per theme and would go dark-on-dark). Literal `white`-based colors are
// used inside it instead, on purpose.
</script>

<template>
  <div class="auth-shell">
    <aside class="auth-atmosphere" aria-hidden="true">
      <div class="atmosphere-glow" />
      <div class="atmosphere-grid" />

      <div class="brand-mark">
        <span class="brand-mark-icon">
          <svg class="icon-svg"><use :href="iconHref('casino')" /></svg>
        </span>
        <span class="brand-mark-text">BasixMeeple</span>
      </div>

      <!-- A small constellation of the app's own feature icons (dice/session
           logging, expansions, card decks, leaderboard) drifting gently —
           ambient motion that visualizes "a living collection", not
           decoration for its own sake. Icon choices are also tuned for
           legibility at this size: silhouettes with a distinct outline
           (extension/cards_stack/leaderboard) read far better tiny than
           icons that are mostly fine internal detail. -->
      <div class="floating-pieces">
        <span class="piece piece-a"><svg class="icon-svg"><use :href="iconHref('casino')" /></svg></span>
        <span class="piece piece-b"><svg class="icon-svg"><use :href="iconHref('extension')" /></svg></span>
        <span class="piece piece-c"><svg class="icon-svg"><use :href="iconHref('cards_stack')" /></svg></span>
        <span class="piece piece-d"><svg class="icon-svg"><use :href="iconHref('leaderboard')" /></svg></span>
      </div>

      <div class="atmosphere-copy">
        <span class="atmosphere-eyebrow">Brettspiel-Sammlung</span>
        <p class="atmosphere-tagline">Jedes Spiel, jede Leihe, jeder Spieleabend — an einem Ort.</p>
      </div>
    </aside>

    <div class="auth-content">
      <slot />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.auth-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--background);
}

.auth-atmosphere {
  position: relative;
  overflow: hidden;
  isolation: isolate;
  flex-shrink: 0;
  min-height: 14rem;
  padding: calc(1.25rem + env(safe-area-inset-top)) 1.5rem 2.75rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 1.5rem;
  border-radius: 0 0 1.75rem 1.75rem;
  background: linear-gradient(
    160deg,
    var(--primary-dark),
    color-mix(in srgb, var(--accent-color) 38%, var(--primary-dark)) 75%
  );
}

.atmosphere-glow {
  position: absolute;
  top: -32%;
  right: -18%;
  width: 60vw;
  height: 60vw;
  max-width: 24rem;
  max-height: 24rem;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--accent-color) 55%, transparent), transparent 70%);
  pointer-events: none;
}

// A faint dot grid — a nod to dice pips / a game-board lattice — fading out
// toward the edges so it reads as texture, not a pattern fighting the copy.
.atmosphere-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(color-mix(in srgb, white 16%, transparent) 1px, transparent 1px);
  background-size: 22px 22px;
  mask-image: radial-gradient(ellipse at 20% 15%, black 0%, transparent 70%);
  -webkit-mask-image: radial-gradient(ellipse at 20% 15%, black 0%, transparent 70%);
  pointer-events: none;
}

.brand-mark {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: fit-content;
  color: white;
}

.brand-mark-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: color-mix(in srgb, white 16%, transparent);

  .icon-svg {
    width: 1.125rem;
    height: 1.125rem;
    color: white;
  }
}

.brand-mark-text {
  font-weight: 800;
  letter-spacing: -0.01em;
  font-size: 1.0625rem;
}

.floating-pieces {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.piece {
  position: absolute;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: color-mix(in srgb, white 12%, transparent);
  border: 1px solid color-mix(in srgb, white 18%, transparent);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  color: color-mix(in srgb, white 88%, transparent);
  animation: piece-float 7s ease-in-out infinite;

  .icon-svg {
    width: 1.125rem;
    height: 1.125rem;
  }
}

.piece-a {
  width: 3.5rem;
  height: 3.5rem;
  top: 10%;
  right: 14%;
  animation-duration: 8s;

  .icon-svg {
    width: 1.625rem;
    height: 1.625rem;
  }
}

.piece-b {
  width: 2.5rem;
  height: 2.5rem;
  top: 46%;
  right: 30%;
  animation-duration: 6.5s;
  animation-delay: 0.6s;

  .icon-svg {
    width: 1.1875rem;
    height: 1.1875rem;
  }
}

.piece-c {
  width: 3rem;
  height: 3rem;
  top: 62%;
  right: 8%;
  animation-duration: 7.5s;
  animation-delay: 1.2s;

  .icon-svg {
    width: 1.375rem;
    height: 1.375rem;
  }
}

.piece-d {
  width: 2.25rem;
  height: 2.25rem;
  top: 6%;
  right: 42%;
  animation-duration: 6s;
  animation-delay: 0.3s;

  .icon-svg {
    width: 1.0625rem;
    height: 1.0625rem;
  }
}

@keyframes piece-float {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-10px) rotate(4deg); }
}

.atmosphere-copy {
  position: relative;
  z-index: 1;
  max-width: 22rem;
}

.atmosphere-eyebrow {
  display: block;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: color-mix(in srgb, white 68%, transparent);
  margin-bottom: 0.4rem;
}

.atmosphere-tagline {
  font-size: 1.0625rem;
  font-weight: 600;
  line-height: 1.4;
  color: white;
  margin: 0;
}

.auth-content {
  position: relative;
  flex: 1;
  display: flex;
  justify-content: center;
  padding: 0 1.25rem calc(2rem + env(safe-area-inset-bottom));
  // Pull the card up over the atmosphere band's rounded bottom edge — the
  // "overlap the section boundary" move that ties the two panes together
  // instead of stacking them as two flatly separate blocks.
  margin-top: -1.5rem;
}

@media (prefers-reduced-motion: reduce) {
  .piece {
    animation: none;
  }
}

// Desktop: an editorial split screen rather than a scaled-up stacked band —
// the atmosphere pane becomes a fixed, full-height rail with a straight
// (not rounded) edge, and the card centers in the remaining space.
@media (min-width: 960px) {
  .auth-shell {
    flex-direction: row;
  }

  .auth-atmosphere {
    flex: 0 0 42%;
    min-height: 100dvh;
    padding: 3rem;
    border-radius: 0;
  }

  .atmosphere-copy {
    max-width: 24rem;
  }

  .atmosphere-tagline {
    font-size: 1.375rem;
  }

  .auth-content {
    flex: 1;
    align-items: center;
    margin-top: 0;
    padding: 3rem;
  }
}
</style>
