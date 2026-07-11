function getTimestamp(path: string) {
  const fileName = path.split('/').pop() ?? path;
  const match = fileName.match(/\d{13}/);

  return match ? Number(match[0]) : undefined;
}

function compareImagePaths(pathA: string, pathB: string, direction: 1 | -1) {
  const timestampA = getTimestamp(pathA);
  const timestampB = getTimestamp(pathB);

  if (timestampA !== undefined && timestampB !== undefined) {
    return (timestampA - timestampB) * direction || pathA.localeCompare(pathB);
  }

  if (timestampA !== undefined) {
    return -1;
  }

  if (timestampB !== undefined) {
    return 1;
  }

  return pathA.localeCompare(pathB);
}

export function compareImagePathsByTimestamp(pathA: string, pathB: string) {
  return compareImagePaths(pathA, pathB, 1);
}

export function compareImagePathsByTimestampDescending(pathA: string, pathB: string) {
  return compareImagePaths(pathA, pathB, -1);
}
