# Frontend (Vue 3 + Inertia + Tailwind 4)

## Índice

- [Estructura de datos y responsabilidades](#estructura-de-datos-y-responsabilidades)
- [Hallazgos](#hallazgos)
- [Verificaciones de seguridad y anti-spoiler](#verificaciones-de-seguridad-y-anti-spoiler)
- [Buenas prácticas del frontend](#buenas-prácticas-del-frontend)

---

## Estructura de datos y responsabilidades

| Archivo | Responsabilidad observada |
|---|---|
| `resources/js/pages/Home.vue:26-85` | Estado de petición (`pulling`), revelado (`showReveal`) y cálculo de probabilidades; delega UI a componentes |
| `resources/js/pages/Album.vue:21-71` | Filtros en memoria sobre 84 figuras; sin navegación al cambiar rareza |
| `resources/js/Layouts/AppLayout.vue:16-22` | Lee `player` y `url` de Inertia; compone navegación y avisos |
| `resources/js/lib/rarity.js:1-75` | Metadatos de rareza y formato de sueldos |
| `resources/js/lib/themes.js:67-109` | Nueve paletas, persistencia en `localStorage` y actualización de `theme-color`/favicon |
| `resources/js/composables/useDismissableOverlay.js:17-53` | Escape y bloqueo de scroll compartidos |
| `resources/css/app.css:617-626` | Animaciones y `prefers-reduced-motion` |

El importe de 1 SM **no está duplicado** en JS: `app.js:25` lo toma de `props.initialPage.props.gacha.salary.amount`, que viene de `config/gachapon.php` vía `HandleInertiaRequests.php:68-73`.

---

## Hallazgos

<a id="fe-01"></a>
### [FE-01] Overlays `aria-modal` sin focus trap ni restauración de foco

- **Severidad:** Media
- **Capa:** Frontend / Accesibilidad
- **Referencia:** WCAG 2.1 SC 2.1.2 (No Keyboard Trap) / 2.4.3 (Focus Order); CWE-1021
- **Ubicación:** `resources/js/Components/PullReveal.vue:47-53`, `resources/js/Components/CharacterModal.vue:58-64`, `resources/js/composables/useDismissableOverlay.js:17-53`
- **Descripción:** los overlays declaran `role="dialog"` y `aria-modal="true"`, pero el composable solo gestiona Escape y `overflow: hidden`. No mueven el foco al abrir, no lo confinan dentro del diálogo ni lo devuelven al disparador al cerrar.
- **Evidencia:**
  ```vue
  // PullReveal.vue:47-53
  useDismissableOverlay({ active: () => true, onEscape: close });
  ...
  <div class="fixed inset-0 z-50 ..." role="dialog" aria-modal="true" aria-label="Resultado de la tirada">
  ```
  ```js
  // useDismissableOverlay.js:36-51
  function alPulsarTecla(event) {
      if (event.key === 'Escape' && active?.()) {
          onEscape?.();
      }
  }
  // No focus() / focusin handler / stored trigger anywhere in the composable.
  ```
- **Impacto / Por qué importa:** un usuario de teclado puede seguir tabulando por botones detrás del modal y activar «Tirar ×10» o navegar a `/coleccion` sin ver qué hace. Un lector de pantalla anuncia `aria-modal` pero el foco real queda fuera, en contradicción con la experiencia prometida.
- **Escenario de explotación o fallo:** tras una tirada se abre el revelado; el usuario pulsa Tab varias veces y termina en el botón de bonificación del fondo, sin darse cuenta de que ya no está en el diálogo.
- **Recomendación:** extender `useDismissableOverlay` para guardar `document.activeElement`, enfocar el primer elemento interactivo del diálogo, atrapar `Tab` dentro de él y restaurar el foco al desmontar. Alternativamente usar `<dialog>` nativo con `showModal()`.
- **Ejemplo de corrección:**
  ```js
  // after: inside useDismissableOverlay({ container, active, onEscape })
  let previousFocus = null;

  watch(active, (open) => {
      if (open) {
          previousFocus = document.activeElement;
          container.value?.querySelector('button, [href], input')?.focus();
      } else {
          previousFocus?.focus?.();
      }
  }, { immediate: true });

  function trapTab(event) {
      if (event.key !== 'Tab' || !container.value) return;
      const focusable = [...container.value.querySelectorAll('button, [href], input')];
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
      }
  }
  ```
- **Esfuerzo estimado:** M
- **Estado de verificación:** **Remediado** el 26/11: el composable confina el foco y lo restaura; comprobado con un DOM falso (foco inicial, `Tab`, `Shift+Tab`, entrada desde fuera y cierre) y build correcto. Prueba en navegador/lector real **NO VERIFICADA**.

<a id="fe-02"></a>
### [FE-02] Google Fonts externas sin consentimiento ni allowlist de CSP

- **Severidad:** Baja
- **Capa:** Frontend / Privacidad
- **Referencia:** GDPR Art. 6 / CWE-200 (Exposure of Sensitive Information); OWASP A05
- **Ubicación:** `resources/views/app.blade.php:27-29`
- **Descripción:** cada carga de página preconecta y descarga CSS desde `fonts.googleapis.com` y `fonts.gstatic.com`. No hay aviso de consentimiento, autocarga ni política de CSP que limite los orígenes.
- **Evidencia:**
  ```html
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  ```
- **Impacto / Por qué importa:** se comparte IP y User-Agent con un tercero antes de que el usuario interactúe. Además, CFG-03 no define CSP, así que no hay allowlist que limite de dónde puede venir código o estilos.
- **Escenario de explotación o fallo:** un usuario con política estricta de privacidad entra al sitio; se registra una petición a Google antes de cualquier consentimiento. O el proveedor queda inaccesible y la tipografía cambia sin degradación controlada.
- **Recomendación:** auto-hospedar Cinzel e Inter en `public/fonts` y servirlos con @font-face, o condicionar la carga al consentimiento. Si se mantienen externas, incluir `fonts.googleapis.com`/`fonts.gstatic.com` en la CSP de CFG-03.
- **Ejemplo de corrección:**
  ```html
  <!-- before -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- after -->
  <link rel="preload" href="/fonts/inter-var.woff2" as="font" type="font/woff2" crossorigin>
  ```
  ```css
  /* after: resources/css/app.css */
  @font-face {
      font-family: 'Inter';
      src: url('/fonts/inter-var.woff2') format('woff2');
      font-display: swap;
  }
  ```
- **Esfuerzo estimado:** S/M
- **Estado de verificación:** Verificado; requisito legal local **NO VERIFICADO** (ver COMP-01).

<a id="fe-03"></a>
### [FE-03] Temporizador de bonificación activo incluso cuando ya es reclamable

- **Severidad:** Baja
- **Capa:** Frontend / Rendimiento
- **Referencia:** CWE-400 (Uncontrolled Resource Consumption) en cliente
- **Ubicación:** `resources/js/Components/BonusClaim.vue:21-26`
- **Descripción:** el componente crea un `setInterval` de 1 segundo al montarse y solo lo limpia al desmontarse. Sigue ejecutándose aunque `seconds === 0`, la pestaña esté en segundo plano o el botón esté deshabilitado por `processing`.
- **Evidencia:**
  ```js
  // BonusClaim.vue:21-26
  const now = ref(Date.now());
  const ticker = setInterval(() => {
      now.value = Date.now();
  }, 1000);

  onBeforeUnmount(() => clearInterval(ticker));
  ```
- **Impacto / Por qué importa:** re-render innecesario cada segundo en todas las páginas que montan el componente. En móvil afecta batería; en pestañas ocultas los navegadores estrangulan el timer, pero el trabajo sigue programado. No es funcionalmente incorrecto.
- **Escenario de explotación o fallo:** el jugador reclama la bonificación y deja la pestaña abierta ocho horas; el intervalo sigue disparando actualizaciones de `now` sin cambio visible.
- **Recomendación:** detener el intervalo cuando `seconds` llega a 0 y reiniciarlo cuando `readyAt` cambie; opcionalmente pausar con `document.visibilityState === 'hidden'`.
- **Ejemplo de corrección:**
  ```js
  // before
  const ticker = setInterval(() => { now.value = Date.now(); }, 1000);

  // after
  let ticker = null;
  function startTicker() {
      if (ticker === null) ticker = setInterval(() => { now.value = Date.now(); }, 1000);
  }
  function stopTicker() {
      clearInterval(ticker);
      ticker = null;
  }
  watch(seconds, (value) => value > 0 ? startTicker() : stopTicker(), { immediate: true });
  ```
- **Esfuerzo estimado:** S
- **Estado de verificación:** Verificado.

---

## Verificaciones de seguridad y anti-spoiler

| Verificación | Resultado | Evidencia |
|---|---|---|
| `v-html`, `innerHTML`, `document.write`, `dangerouslySetInnerHTML` | Sin coincidencias en `resources/` | `git grep` |
| `{!! !!}` en Blade | Sin coincidencias | `git grep` |
| Interpolación de datos del servidor | Vue usa `{{ }}`; Blade usa `{{ }}` | `Home.vue`, `Album.vue`, `app.blade.php:34-35` |
| Anti-spoiler en colección | `image` y `extract` son `null` si `owned === false` | `Character.php:93-96` |
| Anti-spoiler en revelado | `GachaPresenter::reveal()` reutiliza `toCard()` | `GachaPresenter.php:76-83` |
| Secretos en `localStorage` | Solo `gacha-theme` | `themes.js:67,77,91`, `app.blade.php:12` |
| Script inline del tema | Valida el valor con `/^[a-z0-9-]+$/` antes de aplicarlo | `app.blade.php:14-16` |
| Sanitización posterior del tema | `findTheme()` cae a `DEFAULT_THEME` si el id no existe | `themes.js:71-79` |
| `prefers-reduced-motion` | Regla global presente | `app.css:619-626` |

El riesgo de XSS se considera **bajo** en el estado actual: no hay renderizado HTML dinámico. La única superficie es el script inline de tema, mitigado por la regex y por `findTheme()`.

---

## Buenas prácticas del frontend

- **Páginas delgadas:** `Home.vue` y `Album.vue` coordinan datos y eventos; la presentación vive en `Components/` según `AGENTS.md:7`.
- **Composable bien justificado:** `useDismissableOverlay.js:3-15` explica por qué existe (evitar listeners colgados y scroll trabado) y limpia listeners en `onBeforeUnmount`.
- **Recuperación de imágenes:** `CharacterPortrait.vue:32-44` cae a `PortraitFallback` ante `@error` y reinicia el intento cuando cambia `props.image`.
- **Accesibilidad parcial pero real:** `role="dialog"`, `aria-modal`, `aria-label` y `aria-live="polite"` en `ToastNotice.vue:48`. Falta solo el focus trap (FE-01).
- **Temas con tokens:** `rarity.js:1-75` usa `var(--color-rare-*)` en vez de hexadecimales fijos, cumpliendo `AGENTS.md:8`.
- **Sin duplicación de economía:** el sueldo se inyecta desde backend (`app.js:24-25`).
- **Protección del revelado:** `Home.vue:47-50,72-78` bloquea doble envío mientras `pulling` y `PullReveal.vue:40-44` respeta `busy`.

---

## Verificación en vivo

- `npm run build`: **649 módulos transformados**, manifest y assets generados sin errores.
- `GET /coleccion` devolvió HTML 200 con el payload Inertia:

```json
{"component":"Album","props":{"player":{...},"characters":[{"id":34,"owned":false,"quantity":0,"image":null,"extract":null,"wiki_url":"https://es.wikipedia.org/wiki/Agustin_Pio_Barrios"}]}}
```

Esto confirma por HTTP que las figuras no descubiertas viajan sin imagen ni extracto (FE / anti-spoiler). El mismo HTML incluye `preconnect` a `fonts.googleapis.com` y la hoja de `fonts.gstatic.com`, confirmando FE-02 en la respuesta real.
- `GET /storage/characters/kiveve.jpg` respondió `403`: no hay archivos ni enlace público desplegado en este workspace (OPS-04).
