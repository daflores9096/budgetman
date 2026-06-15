import { useEffect, useState } from 'react';
import { IncomeSection } from '../sections/LedgerSections.jsx';

export default function IncomesPage({ ctx }) {
  const [listLoading, setListLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        ctx.setError('');
        setListLoading(true);
        await ctx.reloadMonthly();
      } catch (err) {
        if (!cancelled) ctx.setError(err.message || 'Error al cargar ingresos');
      } finally {
        if (!cancelled) setListLoading(false);
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
      loading={listLoading}
      onChanged={async () => {
        await ctx.reloadMonthly();
      }}
      setError={ctx.setError}
      setLoading={ctx.setLoading}
    />
  );
}

