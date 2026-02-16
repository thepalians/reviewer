/**
 * ReviewFlow Service Worker
 * Handles caching, offline support, and push notifications
 */

const CACHE_NAME = 'reviewflow-v2.0.0';
const OFFLINE_URL = '/reviewer/offline.html';

// Files to cache for offline access
const STATIC_CACHE = [
  '/reviewer/',
  '/reviewer/index.php',
  '/reviewer/user/',
  '/reviewer/user/index.php',
  '/reviewer/user/wallet.php',
  '/reviewer/user/referral.php',
  '/reviewer/user/profile.php',
  '/reviewer/user/notifications.php',
  '/reviewer/offline.html',
  '/reviewer/assets/css/style.css',
  '/reviewer/assets/js/app.js',
  '/reviewer/assets/img/icon-192.png',
  '/reviewer/assets/img/icon-512.png',
  '/reviewer/manifest.json'
];

// Dynamic cache - pages that change frequently
const DYNAMIC_CACHE = 'reviewflow-dynamic-v1';
const DYNAMIC_CACHE_LIMIT = 50;

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing Service Worker...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_CACHE).catch(err => {
          console.log('[SW] Some assets failed to cache:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating Service Worker...');
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name !== CACHE_NAME && name !== DYNAMIC_CACHE)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip chrome-extension and other non-http(s) requests
  if (!url.protocol.startsWith('http')) {
    return;
  }
  
  // Skip API calls and form submissions (always fetch from network)
  if (url.pathname.includes('/api/') || 
      url.pathname.includes('/chatbot/') ||
      url.pathname.includes('/logout.php') ||
      request.headers.get('Content-Type')?.includes('multipart/form-data')) {
    event.respondWith(
      fetch(request)
        .catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }
  
  // For HTML pages - Network first, fallback to cache
  if (request.headers.get('Accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Clone and cache the response
          const responseClone = response.clone();
          caches.open(DYNAMIC_CACHE)
            .then((cache) => {
              cache.put(request, responseClone);
              limitCacheSize(DYNAMIC_CACHE, DYNAMIC_CACHE_LIMIT);
            });
          return response;
        })
        .catch(() => {
          return caches.match(request)
            .then((cachedResponse) => {
              return cachedResponse || caches.match(OFFLINE_URL);
            });
        })
    );
    return;
  }
  
  // For other assets - Cache first, fallback to network
  event.respondWith(
    caches.match(request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        
        return fetch(request)
          .then((response) => {
            // Don't cache non-success responses
            if (!response || response.status !== 200) {
              return response;
            }
            
            // Clone and cache
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE)
              .then((cache) => {
                cache.put(request, responseClone);
              });
            
            return response;
          })
          .catch(() => {
            // Return offline placeholder for images
            if (request.url.match(/\.(jpg|jpeg|png|gif|svg|webp)$/)) {
              return caches.match('/reviewer/assets/img/offline-placeholder.png');
            }
          });
      })
  );
});

// Limit cache size
function limitCacheSize(cacheName, maxItems) {
  caches.open(cacheName)
    .then((cache) => {
      cache.keys()
        .then((keys) => {
          if (keys.length > maxItems) {
            cache.delete(keys[0])
              .then(() => limitCacheSize(cacheName, maxItems));
          }
        });
    });
}

// Push notification event
self.addEventListener('push', (event) => {
  console.log('[SW] Push received');
  
  let data = {
    title: 'ReviewFlow',
    body: 'You have a new notification',
    icon: '/reviewer/assets/img/icon-192.png',
    badge: '/reviewer/assets/img/badge-72.png',
    vibrate: [100, 50, 100],
    data: {
      url: '/reviewer/user/'
    }
  };
  
  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      data.body = event.data.text();
    }
  }
  
  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    vibrate: data.vibrate,
    data: data.data,
    actions: [
      { action: 'open', title: 'Open' },
      { action: 'close', title: 'Close' }
    ],
    tag: 'reviewflow-notification',
    renotify: true
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked');
  
  event.notification.close();
  
  if (event.action === 'close') {
    return;
  }
  
  const urlToOpen = event.notification.data?.url || '/reviewer/user/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Check if app is already open
        for (const client of clientList) {
          if (client.url.includes('/reviewer/') && 'focus' in client) {
            client.navigate(urlToOpen);
            return client.focus();
          }
        }
        // Open new window
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Background sync for offline form submissions
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync:', event.tag);
  
  if (event.tag === 'sync-messages') {
    event.waitUntil(syncMessages());
  }
  
  if (event.tag === 'sync-tasks') {
    event.waitUntil(syncTasks());
  }
});

// Sync messages when back online
async function syncMessages() {
  try {
    const cache = await caches.open('reviewflow-offline-data');
    const requests = await cache.keys();
    
    for (const request of requests) {
      if (request.url.includes('/messages/')) {
        const response = await cache.match(request);
        const data = await response.json();
        
        await fetch(request, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        
        await cache.delete(request);
      }
    }
  } catch (error) {
    console.log('[SW] Sync failed:', error);
  }
}

// Sync tasks when back online
async function syncTasks() {
  // Similar implementation for task submissions
}

// Message from main thread
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);
  
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
  
  if (event.data.action === 'clearCache') {
    caches.keys().then((names) => {
      names.forEach((name) => caches.delete(name));
    });
  }
});

console.log('[SW] Service Worker loaded');
