export function getImageTimestamp(path: string) {
  const fileName = path.split('/').pop() ?? path;
  const digitGroups = fileName.match(/\d{13,}/g) ?? [];
  const minimumTimestamp = Date.UTC(2000, 0, 1);
  const maximumTimestamp = Date.now() + 366 * 24 * 60 * 60 * 1000;

  for (const group of digitGroups) {
    for (let index = 0; index <= group.length - 13; index += 1) {
      const timestamp = Number(group.slice(index, index + 13));

      if (timestamp >= minimumTimestamp && timestamp <= maximumTimestamp) {
        return timestamp;
      }
    }
  }

  return undefined;
}

export function getLatestImageDate(paths: string[]) {
  const latestTimestamp = paths.reduce<number | undefined>((latest, path) => {
    const timestamp = getImageTimestamp(path);

    return timestamp !== undefined && (latest === undefined || timestamp > latest)
      ? timestamp
      : latest;
  }, undefined);

  return latestTimestamp === undefined ? undefined : new Date(latestTimestamp);
}

function compareImagePaths(pathA: string, pathB: string, direction: 1 | -1) {
  const timestampA = getImageTimestamp(pathA);
  const timestampB = getImageTimestamp(pathB);

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
