<script setup>
/**
 * Máquina de la portada: dibujo, movimiento y estado accesible de la tirada.
 *
 * Se extrajo de Home.vue junto con todo su CSS. La página solo decide si hay
 * una tirada en curso; este componente se encarga de traducir ese estado en
 * palanca, perno y sacudida, y de anunciarlo con `aria-live`.
 */
defineProps({
    active: { type: Boolean, default: false },
});
</script>

<template>
    <div class="machine-shell">
        <div class="machine-glow" />

        <div class="machine" :class="{ 'machine-active': active }">
            <div class="dome">
                <span class="capsule cap-1">★</span>
                <span class="capsule cap-2">✦</span>
                <span class="capsule cap-3">●</span>
                <span class="capsule cap-4">★</span>
                <span class="capsule cap-5">◆</span>
                <div class="dome-shine" />
            </div>

            <div class="machine-base">
                <div class="coin-slot" />
                <div class="machine-plate">
                    <span>GACHA</span>
                    <strong>GUARANÍ</strong>
                </div>
                <div class="knob" :class="{ 'knob-turn': active }">
                    <span class="knob-star">★</span>
                </div>
                <div class="tray" />
            </div>

            <div class="lever" :class="{ 'lever-pull': active }">
                <span class="lever-channel" />
                <span class="lever-handle">
                    <span class="lever-arm" />
                    <span class="lever-ball" />
                </span>
            </div>
        </div>

        <p class="mt-6 text-center text-[10px] uppercase tracking-[0.32em] text-bone-400" role="status" aria-live="polite">
            {{ active ? 'Tirando…' : 'Máquina lista para tirar' }}
        </p>
    </div>
</template>

<style scoped>
.machine-shell {
    position: relative;
    width: 100%;
}

.machine-glow {
    position: absolute;
    inset: 12% 3% 6%;
    border-radius: 999px;
    background: radial-gradient(closest-side, rgb(var(--accent-rgb) / 0.3), transparent 72%);
    filter: blur(34px);
}

.machine {
    position: relative;
    width: 300px;
    height: 432px;
    margin: 0 auto;
    filter: drop-shadow(0 40px 60px rgba(0, 0, 0, 0.65));
}

.dome {
    position: absolute;
    left: 16px;
    right: 16px;
    top: 4px;
    height: 218px;
    overflow: hidden;
    border: 1px solid var(--border-strong);
    border-radius: 50% 50% 24px 24px / 58% 58% 16px 16px;
    background:
        radial-gradient(120% 90% at 28% 10%, rgb(255 255 255 / 0.34), rgb(255 255 255 / 0.06) 38%, transparent 62%),
        linear-gradient(160deg, color-mix(in srgb, var(--color-rare-2) 22%, transparent), color-mix(in srgb, var(--color-night-950) 88%, black) 72%);
    box-shadow:
        inset 0 20px 34px rgba(255, 255, 255, 0.14),
        inset 0 -30px 46px rgba(0, 0, 0, 0.62),
        0 14px 40px rgba(0, 0, 0, 0.45);
}

.dome::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.14), transparent 22%, transparent 78%, rgba(255, 255, 255, 0.08));
    pointer-events: none;
}

.dome-shine {
    position: absolute;
    left: 12%;
    top: 7%;
    width: 34%;
    height: 40%;
    border-radius: 50%;
    background: linear-gradient(140deg, rgba(255, 255, 255, 0.6), transparent 72%);
    filter: blur(7px);
}

.capsule {
    position: absolute;
    display: grid;
    width: 46px;
    height: 46px;
    place-items: center;
    border-radius: 999px;
    font-size: 13px;
    color: rgba(5, 8, 11, 0.5);
    box-shadow:
        inset -7px -10px 14px rgba(0, 0, 0, 0.36),
        inset 7px 9px 13px rgba(255, 255, 255, 0.55),
        0 10px 16px rgba(0, 0, 0, 0.35);
    animation: float-soft 9s ease-in-out infinite;
}

.cap-1 {
    left: 28px;
    bottom: 26px;
    background: radial-gradient(circle at 32% 26%, var(--accent-surface-1), var(--accent-surface-2) 46%, var(--accent-surface-3));
}

.cap-2 {
    left: 96px;
    bottom: 66px;
    width: 40px;
    height: 40px;
    background: radial-gradient(circle at 32% 26%, color-mix(in srgb, var(--color-rare-2) 45%, white), var(--color-rare-2) 48%, color-mix(in srgb, var(--color-rare-2) 65%, black));
    animation-delay: -1.4s;
}

.cap-3 {
    right: 34px;
    bottom: 34px;
    background: radial-gradient(circle at 32% 26%, color-mix(in srgb, var(--color-flag-red) 50%, white), var(--color-flag-red) 48%, color-mix(in srgb, var(--color-flag-red) 60%, black));
    animation-delay: -2.6s;
}

.cap-4 {
    right: 74px;
    bottom: 92px;
    width: 38px;
    height: 38px;
    background: radial-gradient(circle at 32% 26%, var(--color-bone-50), var(--color-bone-200) 48%, var(--color-bone-400));
    animation-delay: -3.4s;
}

.cap-5 {
    left: 46px;
    bottom: 112px;
    width: 34px;
    height: 34px;
    background: radial-gradient(
        circle at 32% 26%,
        color-mix(in srgb, rgb(var(--accent-rgb)) 45%, white),
        rgb(var(--accent-rgb)) 48%,
        color-mix(in srgb, rgb(var(--accent-rgb)) 65%, black)
    );
    animation-delay: -4.2s;
}

.machine-base {
    position: absolute;
    inset: auto 0 0 0;
    height: 228px;
    overflow: hidden;
    border: 1px solid var(--border-strong);
    border-radius: 24px 24px 20px 20px;
    background: linear-gradient(
        180deg,
        var(--color-flag-red) 0%,
        color-mix(in srgb, var(--color-flag-red) 85%, black) 26%,
        color-mix(in srgb, var(--color-flag-red) 60%, black) 70%,
        color-mix(in srgb, var(--color-flag-red) 35%, black) 100%
    );
    box-shadow:
        inset 0 2px 0 rgba(255, 255, 255, 0.24),
        inset 0 -26px 44px rgba(0, 0, 0, 0.55),
        0 30px 50px -20px rgba(0, 0, 0, 0.85);
}

.machine-base::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.35);
    background: linear-gradient(180deg, var(--accent-surface-1), var(--accent-surface-2) 55%, var(--accent-surface-3));
}

.machine-base::after {
    content: '';
    position: absolute;
    top: 15px;
    bottom: 0;
    left: 7%;
    width: 9%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.2), transparent);
}

.machine-plate {
    position: absolute;
    top: 32px;
    left: 50%;
    width: 192px;
    transform: translateX(-50%);
    padding: 8px 0 7px;
    border: 1px solid rgb(var(--accent-rgb) / 0.45);
    border-radius: 10px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--color-night-950) 94%, black), color-mix(in srgb, var(--color-night-800) 94%, black));
    text-align: center;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16), 0 6px 16px rgba(0, 0, 0, 0.4);
}

.machine-plate span {
    display: block;
    font-size: 7px;
    letter-spacing: 0.45em;
    color: var(--accent-surface-2);
}

.machine-plate strong {
    display: block;
    margin-top: 1px;
    font-family: var(--font-display);
    font-size: 17px;
    letter-spacing: 0.15em;
    color: var(--accent-surface-1);
    text-shadow: 0 0 18px rgb(var(--accent-rgb) / 0.45);
}

.knob {
    position: absolute;
    top: 96px;
    left: 50%;
    display: grid;
    width: 74px;
    height: 74px;
    place-items: center;
    transform: translateX(-50%);
    border: 3px solid var(--accent-surface-3);
    border-radius: 50%;
    background: radial-gradient(circle at 32% 26%, var(--accent-surface-1), var(--accent-surface-2) 44%, var(--accent-surface-3) 72%, color-mix(in srgb, var(--accent-surface-3) 65%, black));
    box-shadow:
        0 10px 20px rgba(0, 0, 0, 0.5),
        inset 0 -4px 8px rgba(0, 0, 0, 0.35),
        inset 0 4px 8px rgba(255, 255, 255, 0.45);
    transition: transform 0.55s cubic-bezier(0.2, 1, 0.3, 1);
}

.knob::before {
    content: '';
    position: absolute;
    inset: 9px;
    border: 1px dashed rgba(0, 0, 0, 0.28);
    border-radius: 50%;
}

.knob-star {
    font-size: 24px;
    color: var(--accent-ink);
    text-shadow: 0 1px 0 rgb(255 255 255 / 0.45);
}

.knob-turn {
    transform: translateX(-50%) rotate(220deg);
}

.coin-slot {
    position: absolute;
    bottom: 66px;
    left: 22px;
    width: 26px;
    height: 44px;
    border: 1px solid rgb(var(--accent-rgb) / 0.35);
    border-radius: 6px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--color-flag-red) 18%, black), color-mix(in srgb, var(--color-flag-red) 8%, black));
    box-shadow: inset 0 0 10px #000;
}

.coin-slot::after {
    content: '';
    position: absolute;
    top: 9px;
    bottom: 9px;
    left: 50%;
    width: 2px;
    transform: translateX(-50%);
    border-radius: 2px;
    background: rgb(var(--accent-rgb) / 0.55);
}

.tray {
    position: absolute;
    bottom: 16px;
    left: 50%;
    width: 130px;
    height: 28px;
    transform: translateX(-50%);
    border: 1px solid var(--border-soft);
    border-radius: 8px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--color-night-950) 96%, black), color-mix(in srgb, var(--color-flag-red) 20%, black));
    box-shadow: inset 0 6px 14px rgba(0, 0, 0, 0.9), 0 1px 0 rgba(255, 255, 255, 0.14);
}

.lever {
    position: absolute;
    right: -28px;
    top: 178px;
    width: 56px;
    height: 92px;
}

.lever-channel {
    position: absolute;
    top: 6px;
    bottom: 0;
    left: 20px;
    width: 14px;
    border: 1px solid var(--border-soft);
    border-radius: 999px;
    background: linear-gradient(90deg, color-mix(in srgb, var(--color-night-950) 96%, black), color-mix(in srgb, var(--color-flag-red) 28%, black));
    box-shadow: inset 0 0 8px #000;
}

.lever-handle {
    position: absolute;
    top: 8px;
    left: 23px;
    transform-origin: 3px 0;
    transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1);
}

.lever-arm {
    display: block;
    width: 6px;
    height: 46px;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--accent-surface-3), var(--accent-surface-1), var(--accent-surface-3));
}

.lever-ball {
    position: absolute;
    top: -16px;
    left: -14px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, color-mix(in srgb, var(--color-flag-red) 55%, white), var(--color-flag-red) 52%, color-mix(in srgb, var(--color-flag-red) 60%, black));
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.5), inset 0 3px 6px rgba(255, 255, 255, 0.35);
}

.lever-pull .lever-handle {
    transform: rotate(-34deg);
}

.machine-active .dome {
    animation: machine-shake 0.8s ease-in-out infinite;
}

@keyframes machine-shake {
    0%, 100% { transform: translate(0, 0) rotate(0); }
    25% { transform: translate(-1px, 1px) rotate(-0.2deg); }
    75% { transform: translate(1px, -1px) rotate(0.2deg); }
}
</style>
