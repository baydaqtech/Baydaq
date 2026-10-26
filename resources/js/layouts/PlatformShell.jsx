import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { DirectionProvider } from '@/components/ui/direction';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Toaster } from '@/components/ui/sonner';
import { useClientTheme } from '@/lib/theme';

/**
 * Wraps every platform page (auth, portal, admin): reading direction for the
 * shadcn primitives, tooltips, and toasts for flashed messages.
 *
 * Controllers flash a toast with Inertia::flash('toast', ['type' => 'success', 'message' => '...']).
 */
export default function PlatformShell({ children }) {
  const { dir, suite, theme } = usePage().props;
  useClientTheme(suite?.theme ?? theme);

  useEffect(() => router.on('flash', (event) => {
    const flashed = event.detail.flash?.toast;

    if (flashed?.message) {
      (toast[flashed.type] ?? toast)(flashed.message);
    }
  }), []);

  return (
    <DirectionProvider dir={dir}>
      <TooltipProvider>
        {children}
        <Toaster dir={dir} position={dir === 'rtl' ? 'bottom-left' : 'bottom-right'} />
      </TooltipProvider>
    </DirectionProvider>
  );
}
