import {
  Bell, Box, CreditCard, DatabaseBackup, HardDrive, KeyRound, Mail, MapPin, MessageCircle, MessageSquare, Server, Sparkles,
} from 'lucide-react';

// Icon names stored on catalog services (services.icon) and the component that draws each.
const ICONS = {
  mail: Mail,
  'key-round': KeyRound,
  'message-square': MessageSquare,
  'message-circle': MessageCircle,
  bell: Bell,
  'credit-card': CreditCard,
  sparkles: Sparkles,
  'hard-drive': HardDrive,
  server: Server,
  'database-backup': DatabaseBackup,
  'map-pin': MapPin,
};

export const SERVICE_ICON_NAMES = Object.keys(ICONS);

export function serviceIcon(name) {
  return ICONS[name] ?? Box;
}
