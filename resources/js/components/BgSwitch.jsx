// Temporary control kept from the prototype: switches the side background
// between floating pieces and faded board patches.
export default function BgSwitch() {
  return (
    <div className="bg-switch" role="group" aria-label="مقارنة خلفية الصفحة">
      <span>الخلفية:</span>
      <button type="button" id="bg-pieces" aria-pressed="false">قطع</button>
      <button type="button" id="bg-squares" aria-pressed="true">مربعات</button>
    </div>
  );
}
