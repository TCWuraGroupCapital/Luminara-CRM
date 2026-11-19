// Sample data for the prototype. Every company, person and figure is invented.
// Stage totals (n, v) cover every lead; `deals` holds the largest ones, which the model draws.
// Open with ?stress to try long names and many more deals per stage.

const d = (id, company, title, value, owner = 'Mara Chen') => ({ id, company, title, value, owner });

export const stages = [
  { key: 'new', name: 'New', n: 58, v: 412000, deals: [d(4150, 'Tidewater Marine', 'Fleet tracking', 31500), d(4151, 'Kestrel Farms', 'Cold chain sensors', 21800), d(4152, 'Northwind Café', 'POS upgrade', 14200), d(4153, 'Atlas Movers', 'Route planning', 12900), d(4154, 'Ridgeway Dental', 'Patient kiosks', 9600), d(4155, 'Quarry Gym', 'Door access', 7300), d(4156, 'Lumen Studio', 'Booking system', 6400), d(4157, 'Ember Bakehouse', 'Card terminals', 4800)] },
  { key: 'follow', name: 'Follow up', n: 47, v: 388000, deals: [d(4140, 'Meridian Hotels', 'Guest Wi-Fi', 38200), d(4141, 'Sora Robotics', 'Warehouse scanners', 26600), d(4120, 'Arc Logistics', 'Depot tablets', 18400, 'Ivo Petrak'), d(4142, 'Hollis & Co', 'Office fit-out', 15800), d(4143, 'Pinecrest School', 'Classroom screens', 11200), d(4144, 'Granite Labs', 'Sample tracking', 9900), d(4131, 'Bayview Clinics', 'Reception kiosks', 7900), d(4145, 'Fable Toys', 'Stock scanners', 5400)] },
  { key: 'prospect', name: 'Prospect', n: 39, v: 356000, deals: [d(4130, 'Bluebird Freight', 'Fleet renewal', 33100), d(4132, 'Orchard Health', 'Ward tablets', 24500), d(4133, 'Juniper Legal', 'Document scanners', 17400), d(4134, 'Nova Dental', 'Imaging carts', 12600), d(4177, 'Priya Natarajan', 'Warehouse scanners', 9800), d(4182, 'Priya Natarajan', 'Warehouse scanners (copy)', 9800), d(4135, 'Redfern Cycles', 'Workshop POS', 8700), d(4136, 'Cobalt Print', 'Print queue', 6900)] },
  { key: 'neg', name: 'Negotiation', n: 21, v: 402000, deals: [d(4110, 'Summit Mining', 'Site radios', 72000), d(3961, 'Harbour Freight', 'Coastal fleet renewal', 48000), d(4111, 'Vantage Air', 'Hangar tablets', 41000), d(3975, 'Coastline Cold Storage', 'Dock sensors', 22500, 'Ivo Petrak'), d(4112, 'Larkspur Wines', 'Cellar sensors', 19500), d(4113, 'Copperline', 'Field laptops', 13800), d(3988, 'Westpoint Bakery', 'POS refresh', 6200)] },
  { key: 'won', name: 'Won', n: 31, v: 284000, note: 'this month', deals: [d(4100, 'Pioneer Rail', 'Depot scanners', 52000), d(4101, 'Delta Grocers', 'Checkout refresh', 36000), d(4102, 'Beacon Insurance', 'Branch kiosks', 29700), d(4103, 'Maple Schools', 'Library system', 16200), d(4104, 'Oakridge Vet', 'Clinic tablets', 11400), d(4105, 'Saffron Kitchens', 'Kitchen screens', 8800)] },
  { key: 'lost', name: 'Lost', n: 18, v: 97000, note: 'this month', deals: [d(4090, 'Coral Labs', 'Lab tablets', 21000), d(4091, 'Ironbark Tools', 'Stock scanners', 14600), d(4092, 'Fernhill Spa', 'Booking kiosks', 9200), d(4093, 'Quill Media', 'Edit suite', 5600)] },
];

export const proposals = [
  { id: 'p1', call: 2291, action: 'leads_move_stage', at: '09:02', type: 'move', from: 'neg', to: 'won', dealIds: [3961, 3975, 3988], short: '3 deals → Won',
    kind: 'Mark as won', title: 'Mark 3 deals as won', sentence: 'Harbour Freight, Coastline Cold Storage and Westpoint Bakery sent back signed order forms.',
    reason: 'All three returned signed order forms by email between 11 and 13 November. You asked: “log this week’s signed orders as won.”',
    effects: [['ok', 'The 3 deals move from Negotiation to Won'], ['ok', 'Won this month rises by $76.7k'], ['ok', 'You can move them back afterwards if it was a mistake']] },
  { id: 'p2', call: 2290, action: 'leads_delete', at: '09:03', type: 'remove', dealId: 4182, short: 'Remove the copy',
    kind: 'Remove a duplicate', title: 'Remove a duplicate lead', sentence: 'Priya Natarajan’s “Warehouse scanners” lead is in your CRM twice. The copy has no activity.',
    reason: 'The copy has the same person, email and value as the original, and no calls, emails or notes. You asked: “clean up duplicate leads.”',
    effects: [['ok', 'The original lead and its 6 activities stay as they are'], ['warn', 'The copy is deleted permanently. This can’t be undone.']] },
  { id: 'p3', call: 2293, action: 'leads_move_stage', at: '09:05', type: 'move', from: 'follow', to: 'lost', dealIds: [4120, 4131], short: '2 deals → Lost',
    kind: 'Close as lost', title: 'Close 2 quiet deals as lost', sentence: 'Arc Logistics and Bayview Clinics both told you they chose another supplier.',
    reason: 'Both replied in October that they had signed with someone else, and nobody has been in touch since. You asked: “tidy up deals that are clearly dead.”',
    effects: [['ok', 'The 2 deals move from Follow up to Lost'], ['ok', 'Your open pipeline drops by $26.3k'], ['ok', 'You can move them back afterwards if it was a mistake']] },
];

export const timeline = [
  { at: '08:58', text: 'You asked the agent to log this week’s signed orders, clean up duplicates and tidy dead deals' },
  { at: '09:01', text: 'Looked through Negotiation for signed order forms', sub: 'Found 3 · reading never changes anything', state: 'ok' },
  { at: '09:02', text: 'Suggested marking 3 deals as won', pid: 'p1' },
  { at: '09:03', text: 'Found Priya Natarajan’s lead twice', sub: 'Compared both: same person, email and value', state: 'ok' },
  { at: '09:03', text: 'Suggested removing the copy', pid: 'p2' },
  { at: '09:05', text: 'Checked deals with no contact for 30 days', sub: 'Found 2 that said they chose another supplier', state: 'ok' },
  { at: '09:05', text: 'Suggested closing 2 deals as lost', pid: 'p3' },
];

// ?stress: long legal names, more owners, and 40 extra deals per stage, to see what breaks.
if (new URLSearchParams(location.search).has('stress')) {
  const long = ['Northern Territory Department of Infrastructure, Planning and Logistics', 'Harbour Freight & Coastal Logistics Holdings (Australia) Pty Ltd', 'Saint Vincent’s Private Hospital and Aged Care Foundation', 'Wollongong–Shellharbour Regional Water Authority', 'Ōtautahi Christchurch Community Housing Trust'];
  let id = 9000;
  stages.forEach((s, i) => {
    for (let k = 0; k < 40; k++) s.deals.push(d(id++, `${long[(i + k) % long.length]} ${k + 1}`, 'Multi-site replacement programme, stage two (subject to board approval)', 3000 + ((k * 7919 + i * 104729) % 90000), k % 3 ? 'Mara Chen' : 'Alexandria Montgomery-Okonkwo'));
    s.n += 40;
    s.v += s.deals.slice(-40).reduce((a, x) => a + x.value, 0);
  });
  stages[3].deals.find(x => x.id === 3961).company = long[1];
}
stages.forEach(s => s.deals.sort((a, b) => b.value - a.value));
