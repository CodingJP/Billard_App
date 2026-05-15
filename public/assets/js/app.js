let deferredPrompt;

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  deferredPrompt = event;
  const installButton = document.getElementById('install-pwa');
  if (installButton) {
    installButton.hidden = false;
  }
});

const installButton = document.getElementById('install-pwa');
if (installButton) {
  installButton.addEventListener('click', async () => {
    if (!deferredPrompt) {
      return;
    }

    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    installButton.hidden = true;
  });
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').then((registration) => {
      registration.update();

      if (registration.waiting) {
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
      }

      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        if (!newWorker) {
          return;
        }
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            newWorker.postMessage({ type: 'SKIP_WAITING' });
          }
        });
      });

      let refreshing = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) {
          return;
        }
        refreshing = true;
        window.location.reload();
      });
    }).catch(() => {
      // Ignore registration errors for local/dev environments.
    });
  });
}

// ─── Web Push Notifications ───────────────────────────────────────────────────

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function subscribePush() {
  const vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
  if (!vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
  const registration = await navigator.serviceWorker.ready;
  const existing = await registration.pushManager.getSubscription();
  if (existing) return;
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') return;
  try {
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    await fetch('api/push-subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(subscription.toJSON()),
    });
    document.getElementById('enable-push')?.setAttribute('hidden', '');
  } catch (_err) {
    // Silently ignore push subscription errors
  }
}

if ('serviceWorker' in navigator && 'PushManager' in window) {
  navigator.serviceWorker.ready.then((reg) => {
    reg.pushManager.getSubscription().then((sub) => {
      const btn = document.getElementById('enable-push');
      const vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
      if (btn && !sub && vapidKey) {
        btn.removeAttribute('hidden');
      }
    });
  });
}

document.getElementById('enable-push')?.addEventListener('click', subscribePush);

