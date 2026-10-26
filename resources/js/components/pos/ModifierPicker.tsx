import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatMoney } from '@/lib/format';
import { useLocale, useT } from '@/lib/i18n';
import type { MenuModifier, MenuProduct } from '@/types/pos';

interface Props {
  product: MenuProduct | null;
  onClose: () => void;
  onAdd: (product: MenuProduct, modifiers: MenuModifier[]) => void;
}

/** Choose a product's modifiers within each group's min/max before it goes into the cart. */
export default function ModifierPicker({ product, onClose, onAdd }: Props) {
  const t = useT();
  const { dir, locale } = useLocale();
  const [chosen, setChosen] = useState<Record<number, number[]>>({});

  if (!product) {
    return null;
  }

  const valid = product.modifier_groups.every((group) => {
    const count = (chosen[group.id] ?? []).length;
    return count >= group.min && count <= group.max;
  });

  function toggle(groupId: number, modifierId: number, max: number) {
    setChosen((current) => {
      const picked = current[groupId] ?? [];
      if (picked.includes(modifierId)) {
        return { ...current, [groupId]: picked.filter((id) => id !== modifierId) };
      }
      return { ...current, [groupId]: max === 1 ? [modifierId] : picked.length < max ? [...picked, modifierId] : picked };
    });
  }

  function add() {
    const modifiers = product!.modifier_groups.flatMap((group) => group.modifiers.filter((modifier) => (chosen[group.id] ?? []).includes(modifier.id)));
    onAdd(product!, modifiers);
    setChosen({});
  }

  return (
    <Dialog open onOpenChange={(open) => { if (!open) { setChosen({}); onClose(); } }}>
      <DialogContent dir={dir} className="max-w-md">
        <DialogHeader><DialogTitle>{product.name}</DialogTitle></DialogHeader>
        <div className="flex max-h-[60vh] flex-col gap-4 overflow-y-auto">
          {product.modifier_groups.map((group) => (
            <fieldset key={group.id} className="flex flex-col gap-2">
              <legend className="mb-1 text-sm font-medium">{group.name} <span className="text-xs text-muted-foreground">({t('pos.sell.choose_hint', { min: group.min, max: group.max })})</span></legend>
              {group.modifiers.map((modifier) => (
                <label key={modifier.id} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                  <Checkbox checked={(chosen[group.id] ?? []).includes(modifier.id)} onCheckedChange={() => toggle(group.id, modifier.id, group.max)} />
                  <span className="flex-1">{modifier.name}</span>
                  {Number(modifier.price) > 0 && <span className="text-muted-foreground">+{formatMoney(modifier.price, locale)}</span>}
                </label>
              ))}
            </fieldset>
          ))}
        </div>
        <DialogFooter><Button onClick={add} disabled={!valid}>{t('pos.sell.add')}</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
