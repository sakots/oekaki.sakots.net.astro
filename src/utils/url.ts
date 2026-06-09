export function withBase(path = '') {
  const base = import.meta.env.BASE_URL.endsWith('/')
    ? import.meta.env.BASE_URL
    : `${import.meta.env.BASE_URL}/`;
  const normalizedPath = path.replace(/^\/+/, '');

  return `${base}${normalizedPath}`;
}

export function getRelativeRoot(pathname: string) {
  const segments = pathname.split('/').filter(Boolean);

  return segments.length === 0 ? './' : '../'.repeat(segments.length);
}

export function withRelativeRoot(pathname: string, path = '') {
  const normalizedPath = path.replace(/^\/+/, '');

  return `${getRelativeRoot(pathname)}${normalizedPath}`;
}
