import { useCallback, useEffect, useMemo, useState } from 'react';

const FX_CACHE_KEY = 'rr_fx_rate_usd_tzs';
const FX_FALLBACK_RATE = 2650;
const FX_MAX_CACHE_AGE_MS = 24 * 60 * 60 * 1000;

export default function useExchangeRate() {
  const [rate, setRate] = useState(FX_FALLBACK_RATE);
  const [source, setSource] = useState('fallback');

  const applyCachedRate = useCallback(() => {
    try {
      const raw = localStorage.getItem(FX_CACHE_KEY);
      if (!raw) return false;
      const cached = JSON.parse(raw);
      if (!cached?.rate || !cached?.fetchedAt) return false;
      if ((Date.now() - cached.fetchedAt) > FX_MAX_CACHE_AGE_MS) return false;
      setRate(Number(cached.rate));
      setSource('cached');
      return true;
    } catch {
      return false;
    }
  }, []);

  const fetchRate = useCallback(async ({ force = false } = {}) => {
    if (!force && applyCachedRate()) return;

    try {
      const response = await fetch('/exchange-rate?base=USD&target=TZS');
      if (!response.ok) throw new Error('Failed to fetch exchange rate');
      const data = await response.json();
      const nextRate = Number(data?.rate);
      if (!nextRate || Number.isNaN(nextRate)) throw new Error('Invalid exchange rate');
      setRate(nextRate);
      setSource('live');
      try {
        localStorage.setItem(FX_CACHE_KEY, JSON.stringify({ rate: nextRate, fetchedAt: Date.now() }));
      } catch {
        // Ignore localStorage write issues.
      }
    } catch {
      if (!applyCachedRate()) {
        setRate(FX_FALLBACK_RATE);
        setSource('fallback');
      }
    }
  }, [applyCachedRate]);

  useEffect(() => {
    fetchRate();
  }, [fetchRate]);

  const formatTzsFromUsd = useCallback((usdAmount) => {
    if (usdAmount == null || Number.isNaN(Number(usdAmount))) return '—';
    const tzs = Math.round(Number(usdAmount) * rate);
    return `TZS ${tzs.toLocaleString()}`;
  }, [rate]);

  const formatCompactTzsFromUsd = useCallback((usdAmount) => {
    if (usdAmount == null || Number.isNaN(Number(usdAmount))) return '—';
    const tzs = Math.round(Number(usdAmount) * rate);
    if (tzs >= 1_000_000_000) return `TZS ${(tzs / 1_000_000_000).toFixed(1)}B`;
    if (tzs >= 1_000_000) return `TZS ${(tzs / 1_000_000).toFixed(1)}M`;
    if (tzs >= 1_000) return `TZS ${(tzs / 1_000).toFixed(0)}K`;
    return `TZS ${tzs.toLocaleString()}`;
  }, [rate]);

  // Full-precision TZS formatter — no exchange rate applied.
  // Use for values already stored in TZS (e.g. maintenance cost, deposits).
  const formatTzs = useCallback((tzsAmount) => {
    if (tzsAmount == null || Number.isNaN(Number(tzsAmount))) return '—';
    return `TZS ${Math.round(Number(tzsAmount)).toLocaleString()}`;
  }, []);

  // Format an amount that is ALREADY in TZS — no exchange rate applied.
  // Use this for values the backend has pre-converted (e.g. monthlyRevenue, overdueBalance).
  const formatCompactTzs = useCallback((tzsAmount) => {
    if (tzsAmount == null || Number.isNaN(Number(tzsAmount))) return '—';
    const tzs = Math.round(Number(tzsAmount));
    if (tzs >= 1_000_000_000) return `TZS ${(tzs / 1_000_000_000).toFixed(1)}B`;
    if (tzs >= 1_000_000)     return `TZS ${(tzs / 1_000_000).toFixed(1)}M`;
    if (tzs >= 1_000)         return `TZS ${(tzs / 1_000).toFixed(0)}k`;
    return `TZS ${tzs.toLocaleString()}`;
  }, []);

  // Display amount in its native currency — no conversion.
  const formatMoney = useCallback((amount, currency = 'USD') => {
    if (amount == null || Number.isNaN(Number(amount))) return '—';
    const n   = Number(amount);
    const cur = (currency || 'USD').toUpperCase();
    if (cur === 'TZS') return `TZS ${Math.round(n).toLocaleString()}`;
    return `${cur} ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }, []);

  const sourceLabel = useMemo(() => {
    if (source === 'live') return 'live';
    if (source === 'cached') return 'cached';
    return 'offline';
  }, [source]);

  return {
    rate,
    source,
    sourceLabel,
    refreshRate: () => fetchRate({ force: true }),
    formatTzsFromUsd,
    formatCompactTzsFromUsd,
    formatTzs,
    formatCompactTzs,
    formatMoney,
  };
}
