function isPrivateIpv4(hostname) {
  const octets = hostname.split('.').map(Number);
  if (
    octets.length !== 4 ||
    octets.some((octet) => !Number.isInteger(octet) || octet < 0 || octet > 255)
  ) {
    return false;
  }
  const first = octets[0] ?? -1;
  const second = octets[1] ?? -1;
  return (
    first === 0 ||
    first === 10 ||
    first === 127 ||
    (first === 100 && second >= 64 && second <= 127) ||
    (first === 169 && second === 254) ||
    (first === 172 && second >= 16 && second <= 31) ||
    (first === 192 && (second === 0 || second === 168)) ||
    (first === 198 && (second === 18 || second === 19)) ||
    (first === 203 && second === 0)
  );
}

function isLocalHostname(hostname) {
  const normalized = hostname.toLowerCase().replace(/^\[|\]$/g, '');
  const ipv4MappedAddress = normalized.match(/^::ffff:([0-9a-f]{1,4}):([0-9a-f]{1,4})$/);
  const mappedIpv4 = ipv4MappedAddress
    ? [
        Number.parseInt(ipv4MappedAddress[1], 16) >> 8,
        Number.parseInt(ipv4MappedAddress[1], 16) & 255,
        Number.parseInt(ipv4MappedAddress[2], 16) >> 8,
        Number.parseInt(ipv4MappedAddress[2], 16) & 255,
      ].join('.')
    : undefined;
  return (
    normalized === 'localhost' ||
    normalized.endsWith('.localhost') ||
    normalized.endsWith('.local') ||
    normalized.endsWith('.test') ||
    normalized.endsWith('.invalid') ||
    normalized === 'example.com' ||
    normalized.endsWith('.example.com') ||
    normalized === 'example.net' ||
    normalized.endsWith('.example.net') ||
    normalized === 'example.org' ||
    normalized.endsWith('.example.org') ||
    isPrivateIpv4(normalized) ||
    (mappedIpv4 !== undefined && isPrivateIpv4(mappedIpv4)) ||
    normalized === '::' ||
    normalized === '::1' ||
    normalized.startsWith('fc') ||
    normalized.startsWith('fd') ||
    normalized.startsWith('fe80:')
  );
}

export function validateProductionApiUrl(value) {
  if (!value?.trim()) {
    throw new Error(
      'Configure EXPO_PUBLIC_API_BASE_URL as a public HTTPS URL before a production build.',
    );
  }

  let parsed;
  try {
    parsed = new URL(value.trim());
  } catch {
    throw new Error('EXPO_PUBLIC_API_BASE_URL is not a valid URL.');
  }

  const path = parsed.pathname.replace(/\/+$/, '');
  if (
    parsed.protocol !== 'https:' ||
    parsed.username !== '' ||
    parsed.password !== '' ||
    parsed.search !== '' ||
    parsed.hash !== '' ||
    !path.endsWith('/api/v1') ||
    isLocalHostname(parsed.hostname)
  ) {
    throw new Error(
      'Production builds require a public HTTPS API URL ending in /api/v1; local and private hosts are not allowed.',
    );
  }

  return value.trim().replace(/\/+$/, '');
}
