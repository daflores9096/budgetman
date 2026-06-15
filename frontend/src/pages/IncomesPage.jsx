import { useEffect } from 'react';
import { IncomeSection } from '../sections/LedgerSections.jsx';

export default function IncomesPage({ ctx }) {
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        ctx.setError('');
        await ctx.reloadMonthly();
      } catch (err) {
        if (!cancelled) ctx.setError(err.message || 'Error al cargar ingresos');
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [ctx.reloadMonthly, ctx.setError]);

  return (
    <IncomeSection
      items={ctx.monthlyDetail?.incomes || []}
      disabled={ctx.loading}
      onChanged={async () => {
        await ctx.reloadMonthly();
        await ctx.reloadDashboard();
      }}
      setError={ctx.setError}
      setLoading={ctx.setLoading}
    />
  );
}

