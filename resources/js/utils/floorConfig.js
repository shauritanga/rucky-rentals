/**
 * Floor configuration utilities — mirrors app/Support/FloorConfig.php
 *
 * Floor code convention:
 *   B{n}  →  Basement n   (sort: n * -100)
 *   G     →  Ground Floor (sort: 0)
 *   M     →  Mezzanine    (sort: 50)
 *   {n}   →  Floor n      (sort: n * 100)
 */

export const DEFAULT_CONFIG = {
  basements: 0,
  has_ground_floor: false,
  has_mezzanine: false,
  upper_floors: 7,
};

/**
 * Normalize a raw floor_config value (object or null/undefined) into a
 * canonical config object with all keys present and valid defaults.
 */
export function parseFloorConfig(raw) {
  const base = raw && typeof raw === 'object' ? raw : {};
  return {
    basements:        Math.max(0, parseInt(base.basements ?? 0, 10) || 0),
    has_ground_floor: Boolean(base.has_ground_floor ?? false),
    has_mezzanine:    Boolean(base.has_mezzanine ?? false),
    upper_floors:     Math.max(1, parseInt(base.upper_floors ?? 7, 10) || 1),
  };
}

/**
 * Generate an ordered floor list from a config.
 * Returns [{id, label, sortOrder}, ...] sorted bottom-to-top.
 */
export function generateFloors(config) {
  const cfg = parseFloorConfig(config);
  const floors = [];

  // Basements deepest first (B2 before B1)
  for (let n = cfg.basements; n >= 1; n--) {
    floors.push({ id: `B${n}`, label: `Basement ${n}`, sortOrder: n * -100 });
  }

  if (cfg.has_ground_floor) {
    floors.push({ id: 'G', label: 'Ground Floor', sortOrder: 0 });
  }

  if (cfg.has_mezzanine) {
    floors.push({ id: 'M', label: 'Mezzanine', sortOrder: 50 });
  }

  for (let n = 1; n <= cfg.upper_floors; n++) {
    floors.push({ id: String(n), label: `Floor ${n}`, sortOrder: n * 100 });
  }

  return floors;
}

/**
 * Return the sort order integer for a single floor ID string.
 * Used for client-side sorting of floor groups.
 */
export function floorSortOrder(id) {
  if (!id) return Number.MAX_SAFE_INTEGER;
  if (id === 'G') return 0;
  if (id === 'M') return 50;
  if (id.startsWith('B')) {
    const n = parseInt(id.slice(1), 10);
    return isNaN(n) || n <= 0 ? Number.MAX_SAFE_INTEGER : n * -100;
  }
  const n = parseInt(id, 10);
  return isNaN(n) ? Number.MAX_SAFE_INTEGER : n * 100;
}
