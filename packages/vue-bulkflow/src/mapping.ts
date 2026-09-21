import type { ImportProfile } from './client';

const aliases: Record<string, string[]> = {
  email_address: ['customer_email', 'email'],
  'ایمیل': ['email'],
  product_sku: ['sku'],
  product_name: ['name'],
  order_number: ['reference'],
  amount: ['total', 'price'],
};

function normalize(value: string): string {
  return value.trim().toLocaleLowerCase().replace(/[\s_-]+/g, '_');
}

export function proposeMapping(headers: string[], profile: ImportProfile): Record<string, string> {
  const mapping: Record<string, string> = {};
  const destinations = new Set<string>();
  for (const [source, destination] of Object.entries(profile.defaultMapping)) {
    if (headers.includes(source) && profile.attributes.includes(destination) && !destinations.has(destination)) {
      mapping[source] = destination;
      destinations.add(destination);
    }
  }

  for (const header of headers) {
    if (mapping[header] !== undefined) continue;

    const normalized = normalize(header);
    const candidates = profile.attributes.includes(normalized)
      ? [normalized]
      : aliases[normalized] ?? [];
    const destination = candidates.find((candidate) => profile.attributes.includes(candidate) && !destinations.has(candidate));

    if (destination !== undefined) {
      mapping[header] = destination;
      destinations.add(destination);
    }
  }

  return mapping;
}
