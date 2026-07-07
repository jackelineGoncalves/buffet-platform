import { Head } from '@inertiajs/react';
import { useState, useEffect, useMemo, useCallback } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function playBeep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
    } catch (_) {}
}

function elapsed(placedAt) {
    const diff = Math.floor((Date.now() - new Date(placedAt)) / 1000);
    if (diff < 60) return `${diff}s`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    return `${Math.floor(diff / 3600)}h`;
}

const OVERDUE_MS = 10 * 60 * 1000;

function StationFilter({ stations, active, onChange }) {
    return (
        <div className="flex flex-wrap gap-2 mb-6">
            <button
                onClick={() => onChange(null)}
                className={`px-3 py-1 rounded text-sm font-medium ${active === null ? 'bg-gray-800 text-white' : 'bg-gray-100 hover:bg-gray-200'}`}
            >
                Todas
            </button>
            {stations.map(station => (
                <button
                    key={station.id}
                    onClick={() => onChange(station.id)}
                    className="px-3 py-1 rounded text-sm font-medium"
                    style={{
                        backgroundColor: active === station.id ? station.color : undefined,
                        color: active === station.id ? '#fff' : undefined,
                    }}
                >
                    {active !== station.id && <span className="inline-block w-2 h-2 rounded-full mr-1" style={{ backgroundColor: station.color }} />}
                    {station.label}
                </button>
            ))}
        </div>
    );
}

function ItemRow({ item, onAdvance }) {
    const buttonLabel = { firing: '▶ En preparación', prep: '▶ Listo', ready: '✓ Servido' }[item.status];
    const allergens = item.order_item_allergens?.map(a => a.label).join(', ');

    return (
        <div className="flex items-start justify-between gap-2 py-2 border-b last:border-0">
            <div className="text-sm min-w-0">
                <span className="font-medium">{item.qty}× {item.name}</span>
                {item.note && <span className="ml-1 text-gray-500 italic">— {item.note}</span>}
                {allergens && <div className="text-xs text-orange-600 mt-0.5">{allergens}</div>}
            </div>
            <button
                onClick={() => onAdvance(item.id, item.status)}
                className="shrink-0 text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 whitespace-nowrap"
            >
                {buttonLabel}
            </button>
        </div>
    );
}

function RoundCard({ round, statusFilter, onAdvance, isNew, isOverdue }) {
    const visibleItems = round.items.filter(i => i.status === statusFilter);
    if (visibleItems.length === 0) return null;

    const borderClass = isNew
        ? 'border-yellow-400 bg-yellow-50'
        : isOverdue
            ? 'border-red-500'
            : 'border-gray-200';

    return (
        <div className={`rounded border p-3 ${borderClass}`}>
            <div className="flex justify-between items-baseline mb-2">
                <span className="font-bold text-sm">Mesa {round.tableCode} · R{round.round}</span>
                <span className="text-xs text-gray-400">{round.elapsed}</span>
            </div>
            {visibleItems.map(item => (
                <ItemRow key={item.id} item={item} onAdvance={onAdvance} />
            ))}
        </div>
    );
}

function Column({ title, rounds, statusFilter, onAdvance, newOrderIds, overdueOrderIds }) {
    const visibleRounds = rounds.filter(r => r.items.some(i => i.status === statusFilter));

    return (
        <div>
            <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">
                {title}
                <span className="ml-2 text-gray-400 font-normal normal-case">({visibleRounds.length})</span>
            </h2>
            <div className="space-y-3">
                {visibleRounds.map(round => (
                    <RoundCard
                        key={round.orderId}
                        round={round}
                        statusFilter={statusFilter}
                        onAdvance={onAdvance}
                        isNew={newOrderIds.has(round.orderId)}
                        isOverdue={overdueOrderIds.has(round.orderId)}
                    />
                ))}
                {visibleRounds.length === 0 && (
                    <p className="text-sm text-gray-400 italic">Sin ítems</p>
                )}
            </div>
        </div>
    );
}

export default function Kitchen({ initialItems, stations }) {
    const [items, setItems] = useState(initialItems);
    const [stationFilter, setStationFilter] = useState(null);
    const [newOrderIds, setNewOrderIds] = useState(new Set());
    const [tick, setTick] = useState(0);

    useEffect(() => {
        const id = setInterval(() => setTick(t => t + 1), 30_000);
        return () => clearInterval(id);
    }, []);

    const handleOrderPlaced = useCallback((e) => {
        playBeep();
        const order = e.order;
        const incoming = (order.order_items ?? []).map(item => ({
            ...item,
            order: {
                id: order.id,
                round: order.round,
                placed_at: order.placed_at,
                dining_session: order.dining_session,
            },
        }));
        setItems(prev => [...prev, ...incoming]);
        setNewOrderIds(prev => new Set([...prev, order.id]));
        setTimeout(() => {
            setNewOrderIds(prev => { const n = new Set(prev); n.delete(order.id); return n; });
        }, 3000);
    }, []);

    const handleItemUpdated = useCallback((e) => {
        setItems(prev =>
            prev
                .map(item => item.id === e.order_item_id ? { ...item, status: e.status } : item)
                .filter(item => item.status !== 'served')
        );
    }, []);

    useEffect(() => {
        if (!window.Echo) return;
        window.Echo.private('kitchen')
            .listen('.OrderPlaced', handleOrderPlaced)
            .listen('.OrderItemStatusUpdated', handleItemUpdated);
        return () => window.Echo.leave('kitchen');
    }, [handleOrderPlaced, handleItemUpdated]);

    const advanceItem = useCallback(async (itemId, currentStatus) => {
        const next = { firing: 'prep', prep: 'ready', ready: 'served' }[currentStatus];
        setItems(prev =>
            prev
                .map(i => i.id === itemId ? { ...i, status: next } : i)
                .filter(i => i.status !== 'served')
        );
        try {
            const res = await fetch(`/kitchen/order-items/${itemId}`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            });
            if (!res.ok) {
                setItems(prev => prev.map(i => i.id === itemId ? { ...i, status: currentStatus } : i));
            }
        } catch {
            setItems(prev => prev.map(i => i.id === itemId ? { ...i, status: currentStatus } : i));
        }
    }, []);

    const rounds = useMemo(() => {
        const map = {};
        items.forEach(item => {
            const oid = item.order_id;
            if (!map[oid]) {
                map[oid] = {
                    orderId: oid,
                    round: item.order.round,
                    tableCode: item.order.dining_session.restaurant_table.code,
                    placedAt: item.order.placed_at,
                    items: [],
                };
            }
            map[oid].items.push(item);
        });
        return Object.values(map)
            .sort((a, b) => new Date(a.placedAt) - new Date(b.placedAt))
            .map(r => ({ ...r, elapsed: elapsed(r.placedAt) }));
    }, [items, tick]);

    const filtered = useMemo(() =>
        stationFilter
            ? rounds
                .map(r => ({ ...r, items: r.items.filter(i => i.station_id === stationFilter) }))
                .filter(r => r.items.length > 0)
            : rounds,
        [rounds, stationFilter]
    );

    const overdueOrderIds = useMemo(() => {
        const ids = new Set();
        rounds.forEach(round => {
            const hasFiring = round.items.some(i => i.status === 'firing');
            if (hasFiring && Date.now() - new Date(round.placedAt) > OVERDUE_MS) {
                ids.add(round.orderId);
            }
        });
        return ids;
    }, [rounds]);

    return (
        <>
            <Head title="Cocina" />
            <div className="p-6 min-h-screen bg-gray-50">
                <h1 className="text-xl font-bold mb-4">Pantalla de cocina</h1>
                <StationFilter stations={stations} active={stationFilter} onChange={setStationFilter} />
                <div className="grid grid-cols-3 gap-6">
                    <Column
                        title="Sin preparar"
                        rounds={filtered}
                        statusFilter="firing"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                    <Column
                        title="En preparación"
                        rounds={filtered}
                        statusFilter="prep"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                    <Column
                        title="Listos para entregar"
                        rounds={filtered}
                        statusFilter="ready"
                        onAdvance={advanceItem}
                        newOrderIds={newOrderIds}
                        overdueOrderIds={overdueOrderIds}
                    />
                </div>
            </div>
        </>
    );
}
