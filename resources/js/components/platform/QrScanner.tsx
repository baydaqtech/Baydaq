import { useEffect, useRef, useState } from 'react';
import { Camera, CameraOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

interface DetectedBarcode { rawValue: string }
interface BarcodeDetectorLike { detect(source: HTMLVideoElement): Promise<DetectedBarcode[]> }
type BarcodeDetectorCtor = new (options: { formats: string[] }) => BarcodeDetectorLike;

/**
 * Reads QR codes with the device camera through the browser's BarcodeDetector (Chrome, Edge,
 * Android, recent Safari). Where the browser has none, the button stays hidden and a USB/Bluetooth
 * scanner typing into the code field still works.
 */
export default function QrScanner({ onCode }: { onCode: (code: string) => void }) {
  const t = useT();
  const video = useRef<HTMLVideoElement>(null);
  const [active, setActive] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const Detector = typeof window !== 'undefined' ? (window as unknown as { BarcodeDetector?: BarcodeDetectorCtor }).BarcodeDetector : undefined;

  useEffect(() => {
    if (!active || !Detector) {
      return;
    }

    let stream: MediaStream | null = null;
    let timer = 0;
    let last = '';
    const detector = new Detector({ formats: ['qr_code'] });

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then((media) => {
      stream = media;

      if (video.current) {
        video.current.srcObject = media;
        void video.current.play();
      }

      timer = window.setInterval(async () => {
        if (!video.current || video.current.readyState < 2) {
          return;
        }

        const [code] = await detector.detect(video.current).catch(() => []);

        // The same card held in front of the camera is read once until another code shows up.
        if (code?.rawValue && code.rawValue !== last) {
          last = code.rawValue;
          onCode(code.rawValue);
          window.setTimeout(() => { last = ''; }, 3000);
        }
      }, 300);
    }).catch(() => {
      setError(t('common.scanner.denied'));
      setActive(false);
    });

    return () => {
      window.clearInterval(timer);
      stream?.getTracks().forEach((track) => track.stop());
    };
  }, [active, Detector, onCode, t]);

  if (!Detector) {
    return null;
  }

  return (
    <div className="flex flex-col items-center gap-2">
      <Button type="button" variant="outline" size="sm" onClick={() => { setError(null); setActive((value) => !value); }}>
        {active ? <><CameraOff data-icon="inline-start" />{t('common.scanner.stop')}</> : <><Camera data-icon="inline-start" />{t('common.scanner.start')}</>}
      </Button>
      {active && <video ref={video} className="aspect-square w-64 rounded-lg bg-black object-cover" muted playsInline />}
      {error && <p className="text-sm text-destructive">{error}</p>}
    </div>
  );
}
