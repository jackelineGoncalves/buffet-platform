import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function elapsed(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1) return 'ahora';
    if (diff === 1) return 'hace 1 min';
    return `hace ${diff} min`;
}

function ReadyCard({ tableCode, items, onServe }) {
    return (
        <div className="rounded-lg border border-green-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
                <span className="text-lg font-bold text-gray-800">
                    Mesa {tableCode}
                </span>
            </div>
            <ul className="space-y-2">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center justify-between gap-4"
                    >
                        <span className="text-sm text-gray-700">
                            <span className="font-medium">{item.qty}×</span>{' '}
                            {item.name}
                            {item.station && (
                                <span className="ml-2 text-xs text-gray-400">
                                    ({item.station.label})
                                </span>
                            )}
                        </span>
                        <button
                            onClick={() => onServe(item.id)}
                            className="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700 active:scale-95"
                        >
                            ✓ Entregado
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function calcBill(session, setting) {
    if (!session || !setting) return null;
    const allItems =
        session.orders?.flatMap((o) => o.order_items ?? []) ?? [];
    const extrasTotal = allItems
        .filter((i) => i.unit_price != null)
        .reduce((sum, i) => sum + parseFloat(i.unit_price) * i.qty, 0);
    const buffetTotal = parseFloat(setting.buffet_price) * session.guests;
    const wasteTotal =
        parseFloat(setting.waste_fee) * (session.waste_count ?? 0);
    const subtotal = buffetTotal + extrasTotal + wasteTotal;
    const tax = subtotal * parseFloat(setting.tax_rate);
    return {
        buffetTotal,
        extrasTotal,
        wasteTotal,
        tax,
        total: subtotal + tax,
        guests: session.guests,
        wasteCount: session.waste_count ?? 0,
        taxRate: parseFloat(setting.tax_rate),
    };
}

function fmt(n) {
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function ServiceRequestCard({ request, setting, onResolve, onPay, onWaste }) {
    const label = request.type === 'bill' ? '🧾 Cuenta' : '🙋 Mesero';
    const tableCode = request.dining_session?.restaurant_table?.code ?? '—';
    const bill =
        request.type === 'bill'
            ? calcBill(request.dining_session, setting)
            : null;

    return (
        <div className="rounded-lg border border-yellow-300 bg-yellow-50 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <span className="font-semibold text-yellow-800">
                            {label}
                        </span>
                        <span className="text-sm text-yellow-700">
                            Mesa {tableCode}
                        </span>
                        <span className="text-xs text-yellow-600">
                            {elapsed(request.requested_at)}
                        </span>
                    </div>

                    {bill && (
                        <table className="mt-3 w-full max-w-xs text-sm text-gray-700">
                            <tbody>
                                <tr>
                                    <td>Buffet ({bill.guests} pax)</td>
                                    <td className="text-right">
                                        {fmt(bill.buffetTotal)}
                                    </td>
                                </tr>
                                {bill.extrasTotal > 0 && (
                                    <tr>
                                        <td>Extras</td>
                                        <td className="text-right">
                                            {fmt(bill.extrasTotal)}
                                        </td>
                                    </tr>
                                )}
                                <tr>
                                    <td>
                                        <span className="mr-2">
                                            Desperdicio ({bill.wasteCount})
                                        </span>
                                        <button
                                            onClick={() => onWaste(request.dining_session.id, -1)}
                                            disabled={bill.wasteCount === 0}
                                            className="rounded border px-1 text-xs disabled:opacity-30"
                                        >
                                            −
                                        </button>
                                        <button
                                            onClick={() => onWaste(request.dining_session.id, 1)}
                                            className="ml-1 rounded border px-1 text-xs"
                                        >
                                            +
                                        </button>
                                    </td>
                                    <td className="text-right">
                                        {fmt(bill.wasteTotal)}
                                    </td>
                                </tr>
                                <tr className="border-t text-gray-500">
                                    <td>
                                        IVA ({(bill.taxRate * 100).toFixed(0)}%)
                                    </td>
                                    <td className="text-right">
                                        {fmt(bill.tax)}
                                    </td>
                                </tr>
                                <tr className="font-bold text-gray-900">
                                    <td>Total</td>
                                    <td className="text-right">
                                        {fmt(bill.total)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    {request.type === 'bill' ? (
                        <button
                            onClick={() =>
                                onPay(request.dining_session.id, request.id)
                            }
                            className="rounded bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700 active:scale-95"
                        >
                            Cobrar y cerrar
                        </button>
                    ) : (
                        <button
                            onClick={() => onResolve(request.id)}
                            className="rounded bg-yellow-600 px-3 py-1 text-xs font-medium text-white hover:bg-yellow-700 active:scale-95"
                        >
                            Resolver
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Floor({ initialReadyItems, initialServiceRequests, setting }) {
    const [readyItems, setReadyItems] = useState(initialReadyItems ?? []);
    const [serviceRequests, setServiceRequests] = useState(
        initialServiceRequests ?? [],
    );

    const serveItem = useCallback(async (itemId) => {
        setReadyItems((prev) => prev.filter((i) => i.id !== itemId));
        try {
            const res = await fetch(`/floor/order-items/${itemId}/serve`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });
            if (!res.ok) {
                router.reload({ only: ['initialReadyItems'] });
            }
        } catch {
            router.reload({ only: ['initialReadyItems'] });
        }
    }, []);

    const resolveRequest = useCallback(async (requestId) => {
        setServiceRequests((prev) => prev.filter((r) => r.id !== requestId));
        try {
            const res = await fetch(
                `/floor/service-requests/${requestId}/resolve`,
                {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                    },
                },
            );
            if (!res.ok) {
                router.reload({ only: ['initialServiceRequests'] });
            }
        } catch {
            router.reload({ only: ['initialServiceRequests'] });
        }
    }, []);

    const adjustWaste = useCallback(async (sessionId, delta) => {
        setServiceRequests((prev) =>
            prev.map((r) =>
                r.dining_session?.id === sessionId
                    ? {
                          ...r,
                          dining_session: {
                              ...r.dining_session,
                              waste_count: Math.max(
                                  0,
                                  (r.dining_session.waste_count ?? 0) + delta,
                              ),
                          },
                      }
                    : r,
            ),
        );
        try {
            const res = await fetch(
                `/floor/dining-sessions/${sessionId}/waste`,
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ delta }),
                },
            );
            if (res.ok) {
                const { waste_count } = await res.json();
                setServiceRequests((prev) =>
                    prev.map((r) =>
                        r.dining_session?.id === sessionId
                            ? {
                                  ...r,
                                  dining_session: {
                                      ...r.dining_session,
                                      waste_count,
                                  },
                              }
                            : r,
                    ),
                );
            } else {
                router.reload({ only: ['initialServiceRequests'] });
            }
        } catch {
            router.reload({ only: ['initialServiceRequests'] });
        }
    }, []);

    const paySession = useCallback(async (sessionId, requestId) => {
        setServiceRequests((prev) => prev.filter((r) => r.id !== requestId));
        try {
            const res = await fetch(
                `/floor/dining-sessions/${sessionId}/payment`,
                {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                    },
                },
            );
            if (!res.ok) {
                router.reload({
                    only: ['initialServiceRequests', 'initialReadyItems'],
                });
            } else {
                setReadyItems((prev) =>
                    prev.filter(
                        (i) => i.order?.dining_session_id !== sessionId,
                    ),
                );
            }
        } catch {
            router.reload({
                only: ['initialServiceRequests', 'initialReadyItems'],
            });
        }
    }, []);

    useEffect(() => {
        if (!window.Echo) return;

        const channel = window.Echo.private('floor')
            .listen('.OrderItemStatusUpdated', (e) => {
                if (e.status === 'served') {
                    setReadyItems((prev) =>
                        prev.filter((i) => i.id !== e.order_item_id),
                    );
                } else if (e.status === 'ready') {
                    router.reload({ only: ['initialReadyItems'] });
                }
            })
            .listen('.ServiceRequestCreated', () => {
                router.reload({ only: ['initialServiceRequests'] });
            });

        const conn = window.Echo.connector.pusher.connection;
        let wasDisconnected = false;
        const onDisconnected = () => {
            wasDisconnected = true;
        };
        const onConnected = () => {
            if (wasDisconnected) {
                wasDisconnected = false;
                router.reload({
                    only: ['initialReadyItems', 'initialServiceRequests'],
                });
            }
        };
        conn.bind('disconnected', onDisconnected);
        conn.bind('connected', onConnected);

        return () => {
            window.Echo.leave('floor');
            conn.unbind('disconnected', onDisconnected);
            conn.unbind('connected', onConnected);
        };
    }, []);

    const byTable = readyItems.reduce((acc, item) => {
        const code =
            item.order?.dining_session?.restaurant_table?.code ?? '—';
        if (!acc[code]) acc[code] = [];
        acc[code].push(item);
        return acc;
    }, {});

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <h1 className="mb-6 text-2xl font-bold text-gray-800">
                Sala — Vista de piso
            </h1>

            {serviceRequests.length > 0 && (
                <section className="mb-8">
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-yellow-700">
                        Solicitudes ({serviceRequests.length})
                    </h2>
                    <div className="space-y-2">
                        {serviceRequests.map((r) => (
                            <ServiceRequestCard
                                key={r.id}
                                request={r}
                                setting={setting}
                                onResolve={resolveRequest}
                                onPay={paySession}
                                onWaste={adjustWaste}
                            />
                        ))}
                    </div>
                </section>
            )}

            <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-green-700">
                    Listos para entregar (
                    {Object.keys(byTable).length > 0
                        ? `${readyItems.length} ítems`
                        : '0 ítems'}
                    )
                </h2>

                {Object.keys(byTable).length === 0 ? (
                    <p className="text-sm text-gray-400">
                        Sin ítems listos por ahora.
                    </p>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(byTable).map(([code, items]) => (
                            <ReadyCard
                                key={code}
                                tableCode={code}
                                items={items}
                                onServe={serveItem}
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
