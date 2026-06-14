import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import { router } from './router.jsx';
import './index.css';

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    import('virtual:pwa-register')
      .then(({ registerSW }) => registerSW({ immediate: false }))
      .catch(() => {});
  });
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
